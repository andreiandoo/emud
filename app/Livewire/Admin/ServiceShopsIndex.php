<?php

namespace App\Livewire\Admin;

use App\Enums\ServicePromotionTier;
use App\Models\ServiceShop;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::admin')]
class ServiceShopsIndex extends Component
{
    use WithPagination;

    public ?int $editingId = null;

    public string $name = '';

    public string $slug = '';

    public string $county = '';

    public string $city = '';

    public string $address = '';

    public string $phone = '';

    public string $email = '';

    public string $website = '';

    public string $description = '';

    public string $specialities = '';

    public bool $fits_parts_bought_here = false;

    public string $shopStatus = 'draft';

    public string $promotion_tier = 'none';

    public ?string $promoted_until = null;

    public string $promotion_notes = '';

    public string $search = '';

    public string $saved = '';

    public function edit(int $id): void
    {
        $shop = ServiceShop::query()->findOrFail($id);

        $this->editingId = $shop->id;
        $this->name = (string) $shop->name;
        $this->slug = (string) $shop->slug;
        $this->county = (string) $shop->county;
        $this->city = (string) $shop->city;
        $this->address = (string) $shop->address;
        $this->phone = (string) $shop->phone;
        $this->email = (string) $shop->email;
        $this->website = (string) $shop->website;
        $this->description = (string) $shop->description;
        $this->specialities = implode(', ', $shop->specialityList());
        $this->fits_parts_bought_here = (bool) $shop->fits_parts_bought_here;
        $this->shopStatus = (string) $shop->status;
        $this->promotion_tier = $shop->promotion_tier->value;
        $this->promoted_until = $shop->promoted_until?->format('Y-m-d');
        $this->promotion_notes = (string) $shop->promotion_notes;
        $this->saved = '';
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['required', 'string', 'max:180', 'regex:/^[a-z0-9-]+$/', Rule::unique('service_shops', 'slug')->ignore($this->editingId)],
            'county' => ['required', 'string', 'max:64'],
            'city' => ['required', 'string', 'max:96'],
            'address' => ['nullable', 'string', 'max:180'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'description' => ['nullable', 'string'],
            'shopStatus' => ['required', Rule::in(['draft', 'published'])],
            'promotion_tier' => ['required', Rule::enum(ServicePromotionTier::class)],
            // A paid tier without an end date would run forever without anyone revisiting it,
            // so the date is required as soon as money is involved.
            'promoted_until' => [Rule::requiredIf($this->promotion_tier !== 'none'), 'nullable', 'date'],
            'promotion_notes' => ['nullable', 'string', 'max:500'],
        ], [
            'slug.regex' => 'Slugul poate conține doar litere mici, cifre și cratime.',
            'promoted_until.required' => 'O listare plătită trebuie să aibă o dată de expirare.',
        ]);

        $shop = $this->editingId === null ? new ServiceShop : ServiceShop::query()->findOrFail($this->editingId);

        // Listed column by column rather than spread from the validated array: the form field
        // is called shopStatus to avoid clashing with Livewire's own status handling, and
        // spreading would mass-assign that name straight at a column that does not exist.
        $shop->fill([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'county' => $data['county'],
            'city' => $data['city'],
            'address' => $data['address'] ?: null,
            'phone' => $data['phone'] ?: null,
            'email' => $data['email'] ?: null,
            'website' => $data['website'] ?: null,
            'description' => $data['description'] ?: null,
            'status' => $data['shopStatus'],
            'promotion_tier' => $data['promotion_tier'],
            'promotion_notes' => $data['promotion_notes'] ?: null,
            'promoted_until' => $data['promotion_tier'] === 'none' ? null : $data['promoted_until'],
            'fits_parts_bought_here' => $this->fits_parts_bought_here,
            'specialities' => $this->parsedSpecialities(),
        ]);

        $shop->save();

        $this->editingId = $shop->id;
        $this->saved = 'Service-ul a fost salvat.';
    }

    public function create(): void
    {
        $this->reset([
            'editingId', 'name', 'slug', 'county', 'city', 'address', 'phone', 'email',
            'website', 'description', 'specialities', 'promotion_notes', 'promoted_until', 'saved',
        ]);
        $this->resetValidation();
        $this->shopStatus = 'draft';
        $this->promotion_tier = 'none';
        $this->fits_parts_bought_here = false;
    }

    public function updatedName(string $value): void
    {
        if ($this->editingId === null && $this->slug === '') {
            $this->slug = Str::slug($value);
        }
    }

    public function render()
    {
        return view('livewire.admin.service-shops-index', [
            'shops' => ServiceShop::query()
                ->when($this->search !== '', fn ($query) => $query->whereRaw('lower(name) like ?', ['%'.mb_strtolower($this->search).'%']))
                ->orderBy('county')->orderBy('city')->orderBy('name')
                ->paginate(25),
            'tiers' => ServicePromotionTier::cases(),
        ]);
    }

    /** @return list<string> */
    private function parsedSpecialities(): array
    {
        return collect(explode(',', $this->specialities))
            ->map(fn (string $item): string => trim($item))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
