<?php

namespace App\Livewire\Storefront;

use App\Directory\AppointmentService;
use App\Directory\LeadTracker;
use App\Directory\ShopStructuredData;
use App\Enums\AppointmentSlot;
use App\Enums\ServiceLeadEventType;
use App\Models\Order;
use App\Models\Service;
use App\Models\ServiceShop;
use App\Storefront\Garage;
use App\Storefront\VehicleContext;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use RuntimeException;

#[Layout('layouts::storefront', ['fullWidth' => true])]
class ServiceShopPage extends Component
{
    public ServiceShop $shop;

    public bool $phoneVisible = false;

    /**
     * The order this fitting is for, carried in the URL by the link on the confirmation page.
     * Held as the checkout token rather than an id: the id would let anyone attach any order.
     */
    #[Url(except: '')]
    public string $order = '';

    public ?int $serviceId = null;

    public ?int $customerVehicleId = null;

    public string $vehicleLabel = '';

    public string $name = '';

    public string $phone = '';

    public string $email = '';

    public string $preferredDate = '';

    public string $preferredSlot = 'anytime';

    public string $message = '';

    public bool $consent = false;

    public string $formError = '';

    /** True when an admin is looking at a listing the public cannot see yet. */
    public bool $preview = false;

    /**
     * Resolved here so a draft listing is a 404 rather than readable by guessing the slug, and
     * so a workshop reached at the wrong city segment lands on its canonical address instead of
     * being served the same page at two URLs.
     *
     * Staff see drafts too, so a listing can be looked at as a customer will see it before it
     * is published.
     */
    public function mount(string $city, string $slug): void
    {
        $staff = (bool) Auth::user()?->isAdmin();

        $this->shop = ServiceShop::query()
            ->when(! $staff, fn ($query) => $query->published())
            ->with(['hours', 'media', 'makes', 'services.serviceCategory', 'services.partsCategory'])
            ->where('slug', $slug)
            ->firstOrFail();

        $this->preview = $this->shop->status !== 'published';

        if ($city !== $this->shop->citySegment()) {
            $this->permanentRedirect($this->shop->url());
        }

        $this->prefill();
    }

    /**
     * A permanent redirect from inside mount().
     *
     * Livewire's own redirect() is always a 302, and these two are permanent moves — an address
     * that changed shape and a workshop reached at the wrong city segment. Throwing the response
     * is Laravel's own mechanism for returning early from somewhere that cannot return a
     * response, which is exactly the position a Livewire mount is in.
     *
     * Built directly rather than through redirect(): inside a component that helper resolves to
     * Livewire's own Redirector, which is not a Response and cannot be thrown.
     */
    private function permanentRedirect(string $url): never
    {
        throw new HttpResponseException(new RedirectResponse($url, 301));
    }

    public function revealPhone(LeadTracker $leads): void
    {
        $this->phoneVisible = true;

        // Counted once per visit per workshop. A number revealed again after scrolling back up
        // is the same lead, and billing a workshop twice for it would be wrong.
        $key = 'service.phone-revealed.'.$this->shop->id;

        if (! Session::has($key)) {
            Session::put($key, true);
            $leads->record($this->shop, ServiceLeadEventType::PhoneReveal);
        }
    }

    public function submit(AppointmentService $appointments)
    {
        $this->formError = '';

        $data = $this->validate([
            'serviceId' => ['nullable', 'integer', 'exists:services,id'],
            'customerVehicleId' => ['nullable', 'integer'],
            'vehicleLabel' => ['nullable', 'string', 'max:120'],
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:190'],
            'preferredDate' => ['nullable', 'date', 'after_or_equal:today'],
            'preferredSlot' => ['required', 'string', 'in:morning,afternoon,anytime'],
            'message' => ['nullable', 'string', 'max:1000'],
            // Not a formality: submitting this hands the customer's name and phone number to a
            // third party, so it has to be an act rather than a default.
            'consent' => ['accepted'],
        ], [
            'name.required' => 'Spune-ne cum te cheamă.',
            'phone.required' => 'Service-ul are nevoie de un număr de telefon ca să te sune.',
            'preferredDate.after_or_equal' => 'Alege o dată de azi înainte.',
            'consent.accepted' => 'Avem nevoie de acordul tău ca să trimitem datele către service.',
        ]);

        try {
            $appointment = $appointments->request($this->shop, [
                'user_id' => Auth::id(),
                'customer_vehicle_id' => $this->ownedVehicleId(),
                'service_id' => $data['serviceId'],
                'order_id' => $this->resolvedOrder()?->id,
                'customer_name' => $data['name'],
                'customer_phone' => $data['phone'],
                'customer_email' => $data['email'] ?: null,
                'vehicle_label' => $this->ownedVehicleId() === null ? ($data['vehicleLabel'] ?: null) : null,
                'preferred_date' => $data['preferredDate'] ?: null,
                'preferred_slot' => $data['preferredSlot'],
                'message' => $data['message'] ?: null,
            ]);
        } catch (RuntimeException $exception) {
            $this->formError = $exception->getMessage();

            return null;
        }

        return $this->redirect(route('storefront.appointment', $appointment->token), navigate: false);
    }

    public function render()
    {
        $garage = Auth::user() === null ? collect() : app(Garage::class)->forUser(Auth::user());

        return view('livewire.storefront.service-shop-page', [
            'schedule' => $this->shop->schedule(),
            'servicesByCategory' => $this->servicesByCategory(),
            'garageVehicles' => $garage,
            'slots' => AppointmentSlot::cases(),
            'linkedOrder' => $this->resolvedOrder(),
            'nearby' => $this->nearby(),
            'structuredData' => ShopStructuredData::for($this->shop),
            'breadcrumbs' => $this->breadcrumbs(),
        ]);
    }

    /**
     * Built here rather than inline in the template.
     *
     * Blade's @json directive takes its argument by matching the first balanced closing paren,
     * so a literal array spread over several lines is cut in half and the compiled view is a
     * parse error — one that only appears when the page is actually rendered.
     *
     * @return array<string, mixed>
     */
    private function breadcrumbs(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Service auto', 'item' => route('storefront.services')],
                ['@type' => 'ListItem', 'position' => 2, 'name' => $this->shop->city, 'item' => route('storefront.services.city', $this->shop->citySegment())],
                ['@type' => 'ListItem', 'position' => 3, 'name' => $this->shop->name, 'item' => $this->shop->url()],
            ],
        ];
    }

    /**
     * The price list, grouped the way a workshop would read it out.
     *
     * @return Collection<string, Collection<int, Service>>
     */
    private function servicesByCategory(): Collection
    {
        return $this->shop->services
            ->sortBy(fn ($service) => [$service->serviceCategory?->position, $service->name])
            ->groupBy(fn ($service) => $service->serviceCategory?->name ?? 'Alte lucrări');
    }

    /** @return Collection<int, ServiceShop> */
    private function nearby(): Collection
    {
        return ServiceShop::query()
            ->published()
            ->where('city_slug', $this->shop->citySegment())
            ->whereKeyNot($this->shop->id)
            ->orderByDesc('fits_parts_bought_here')
            ->orderBy('name')
            ->limit(4)
            ->get();
    }

    /**
     * The order is matched on its checkout token, the same secret the confirmation page is
     * reached by, so a customer can attach an order they can already see and nobody can attach
     * one they cannot.
     */
    private function resolvedOrder(): ?Order
    {
        if ($this->order === '' || ! preg_match('/^[0-9a-f-]{36}$/i', $this->order)) {
            return null;
        }

        return Order::query()->where('checkout_token', $this->order)->first();
    }

    /** Vehicles are only ever attached from the signed-in customer's own garage. */
    private function ownedVehicleId(): ?int
    {
        $user = Auth::user();

        if ($user === null || $this->customerVehicleId === null) {
            return null;
        }

        return $user->vehicles()->whereKey($this->customerVehicleId)->exists() ? $this->customerVehicleId : null;
    }

    private function prefill(): void
    {
        $user = Auth::user();

        if ($user !== null) {
            $this->name = (string) $user->name;
            $this->email = (string) $user->email;
            $this->phone = (string) ($user->phone ?? '');
        }

        $selected = app(VehicleContext::class)->current();

        if ($selected?->customerVehicleId !== null) {
            $this->customerVehicleId = $selected->customerVehicleId;
        } elseif ($selected !== null) {
            $this->vehicleLabel = $selected->label();
        }
    }
}
