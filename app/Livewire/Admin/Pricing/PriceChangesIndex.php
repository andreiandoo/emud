<?php

namespace App\Livewire\Admin\Pricing;

use App\Commerce\Repricer;
use App\Enums\PriceChangeStatus;
use App\Jobs\EvaluateProductAlerts;
use App\Models\PriceChange;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use RuntimeException;

/**
 * Automatic price moves too large to go live unseen, and the log of those that did.
 *
 * The one place a feed error that halves a cost is caught before it halves a price.
 */
#[Layout('layouts::admin')]
class PriceChangesIndex extends Component
{
    use WithPagination;

    #[Url(except: 'pending')]
    public string $status = 'pending';

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function approve(int $changeId, Repricer $repricer): void
    {
        $change = PriceChange::query()->findOrFail($changeId);

        try {
            $repricer->approve($change, Auth::id());
        } catch (RuntimeException $exception) {
            session()->flash('status', $exception->getMessage());

            return;
        }

        EvaluateProductAlerts::dispatch($change->product_id);
        session()->flash('status', 'Prețul nou este activ.');
    }

    public function reject(int $changeId, Repricer $repricer): void
    {
        try {
            $repricer->reject(PriceChange::query()->findOrFail($changeId), Auth::id());
        } catch (RuntimeException $exception) {
            session()->flash('status', $exception->getMessage());

            return;
        }

        session()->flash('status', 'Propunerea a fost respinsă; prețul actual rămâne.');
    }

    public function render()
    {
        return view('livewire.admin.pricing.price-changes-index', [
            'changes' => PriceChange::query()
                ->with(['product:id,name', 'variant:id,sku'])
                ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
                ->latest('id')
                ->paginate(30),
            'counts' => PriceChange::query()
                ->select('status', DB::raw('count(*) as total'))
                ->groupBy('status')
                ->pluck('total', 'status'),
            'statuses' => PriceChangeStatus::cases(),
        ]);
    }
}
