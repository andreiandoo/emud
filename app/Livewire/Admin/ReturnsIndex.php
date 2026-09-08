<?php

namespace App\Livewire\Admin;

use App\Commerce\ReturnService;
use App\Enums\ReturnStatus;
use App\Models\OrderReturn;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

#[Layout('layouts::admin')]
class ReturnsIndex extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $status = '';

    public ?int $notingId = null;

    public string $internalNote = '';

    public string $error = '';

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function transition(int $returnId, string $to, ReturnService $returns): void
    {
        $this->error = '';

        try {
            $returns->transition(
                OrderReturn::query()->findOrFail($returnId),
                ReturnStatus::from($to),
                $this->notingId === $returnId ? ($this->internalNote ?: null) : null,
            );
        } catch (Throwable $exception) {
            // Shown rather than thrown: a refused transition is an ordinary operational answer,
            // not a crash.
            $this->error = $exception->getMessage();
        }

        $this->reset(['notingId', 'internalNote']);
    }

    public function render()
    {
        return view('livewire.admin.returns-index', [
            'returns' => OrderReturn::query()
                ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
                ->with(['order', 'user', 'items.orderItem'])
                ->latest('id')
                ->paginate(20),
            'statuses' => ReturnStatus::cases(),
            'counts' => OrderReturn::query()
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
        ]);
    }
}
