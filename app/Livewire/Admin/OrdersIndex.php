<?php

namespace App\Livewire\Admin;

use App\Models\Order;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::admin')]
class OrdersIndex extends Component
{
    use WithPagination;

    /** @var array<string, string> */
    public const TABS = [
        'all' => 'Toate',
        'pending' => 'În așteptare',
        'confirmed' => 'Confirmate',
        'processing' => 'În lucru',
        'completed' => 'Finalizate',
        'cancelled' => 'Anulate',
    ];

    /** @var array<string, string> */
    public const PAYMENT_STATES = [
        'paid' => 'Plătite',
        'pending' => 'Neplătite',
        'failed' => 'Eșuate',
        'refunded' => 'Rambursate',
    ];

    /** Statuses an operator may set from the list. Cancelling is deliberately not one of them —
     *  it releases stock and notifies the customer, so it belongs on the order itself. */
    public const BULK_STATUSES = ['confirmed' => 'Confirmă', 'processing' => 'Trimite în lucru', 'completed' => 'Finalizează'];

    #[Url]
    public string $search = '';

    #[Url(except: 'all')]
    public string $tab = 'all';

    #[Url(except: '')]
    public string $payment = '';

    /** @var list<int> */
    public array $selected = [];

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'tab', 'payment'], true)) {
            // The selection points at rows that are about to leave the list, and a bulk action
            // against orders the operator can no longer see is the kind of mistake that is only
            // noticed afterwards.
            $this->selected = [];
            $this->resetPage();
        }
    }

    public function selectPage(): void
    {
        $this->selected = $this->pageIds()->all();
    }

    public function clearSelection(): void
    {
        $this->selected = [];
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->payment = '';
        $this->tab = 'all';
        $this->selected = [];
        $this->resetPage();
    }

    public function markOne(int $orderId, string $status): void
    {
        $this->selected = [$orderId];
        $this->markAs($status);
    }

    public function markAs(string $status): void
    {
        abort_unless(array_key_exists($status, self::BULK_STATUSES), 422);

        $orders = Order::query()->whereIn('id', $this->selected)->get(['id', 'status']);

        DB::transaction(function () use ($orders, $status): void {
            foreach ($orders as $order) {
                if ($order->status === $status) {
                    continue;
                }

                DB::table('order_status_history')->insert([
                    'order_id' => $order->id,
                    'user_id' => Auth::id(),
                    'from_status' => $order->status,
                    'to_status' => $status,
                    'note' => 'Modificare în bloc din lista de comenzi.',
                ]);

                $order->update(['status' => $status]);
            }
        });

        session()->flash('success', trans_choice('S-a actualizat :count comandă.|S-au actualizat :count comenzi.', $orders->count(), ['count' => $orders->count()]));
        $this->selected = [];
    }

    public function render()
    {
        return view('livewire.admin.orders-index', [
            'orders' => $this->query()->with('user:id,name')->paginate(25),
            'tabs' => self::TABS,
            'counts' => $this->countsByStatus(),
            'paymentStates' => self::PAYMENT_STATES,
            'bulkStatuses' => self::BULK_STATUSES,
            'totals' => $this->totals(),
        ]);
    }

    private function query(): Builder
    {
        return Order::query()
            ->withCount('items')
            ->when($this->search !== '', function (Builder $query): void {
                $term = '%'.mb_strtolower($this->search).'%';
                $query->where(fn (Builder $inner) => $inner
                    ->whereRaw('lower(number) like ?', [$term])
                    ->orWhereRaw('lower(customer_email) like ?', [$term]));
            })
            ->when($this->tab !== 'all', fn (Builder $query) => $query->where('status', $this->tab))
            ->when($this->payment !== '', fn (Builder $query) => $query->where('payment_status', $this->payment))
            ->latest('id');
    }

    /** @return Collection<int, int> */
    private function pageIds(): Collection
    {
        return $this->query()->paginate(25)->pluck('id');
    }

    /** @return array<string, int> */
    private function countsByStatus(): array
    {
        $counts = Order::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return ['all' => (int) $counts->sum()] + $counts->map(fn ($total) => (int) $total)->all();
    }

    /**
     * Figures for the tiles above the table. Computed over the filtered set rather than the whole
     * table, so they answer a question about what the operator is currently looking at.
     *
     * @return array<string, mixed>
     */
    private function totals(): array
    {
        $currency = (string) config('emud.catalog.default_currency', 'RON');
        $rows = $this->query()->reorder()->get(['grand_total', 'payment_status']);

        $revenue = Money::sum($rows->map(fn (Order $order) => Money::of($order->grand_total, $currency)), $currency);

        return [
            'count' => $rows->count(),
            'revenue' => $revenue,
            'average' => $rows->isEmpty() ? Money::zero($currency) : Money::fromMinor(intdiv($revenue->toMinor(), $rows->count()), $currency),
            'unpaid' => $rows->where('payment_status', 'pending')->count(),
        ];
    }
}
