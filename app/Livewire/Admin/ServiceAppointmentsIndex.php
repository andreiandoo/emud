<?php

namespace App\Livewire\Admin;

use App\Directory\AppointmentService;
use App\Enums\ServiceAppointmentStatus;
use App\Models\ServiceAppointment;
use App\Models\ServiceShop;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

/**
 * The inbox of fitting requests.
 *
 * These are leads a workshop is being sold, so the list is also the evidence: what came in,
 * when, and what was done with it.
 */
#[Layout('layouts::admin')]
class ServiceAppointmentsIndex extends Component
{
    use WithPagination;

    #[Url(except: 'requested')]
    public string $status = 'requested';

    #[Url(except: '')]
    public string $shop = '';

    public string $error = '';

    /** @var array<int, string> */
    public array $notes = [];

    public function updated(string $property): void
    {
        if ($property !== 'page') {
            $this->resetPage();
        }
    }

    /** Named advance() because Livewire\Component already declares transition(). */
    public function advance(int $appointmentId, string $to, AppointmentService $appointments): void
    {
        $this->error = '';

        try {
            $appointments->transition(
                ServiceAppointment::query()->findOrFail($appointmentId),
                ServiceAppointmentStatus::from($to),
                $this->notes[$appointmentId] ?? null,
            );
        } catch (Throwable $exception) {
            // Shown rather than thrown: a refused transition is an ordinary operational answer,
            // not a crash.
            $this->error = $exception->getMessage();
        }

        unset($this->notes[$appointmentId]);
    }

    public function render()
    {
        return view('livewire.admin.service-appointments-index', [
            'appointments' => $this->query()
                ->with(['shop', 'service', 'user', 'vehicle.make', 'vehicle.model', 'order'])
                ->latest('id')
                ->paginate(20),
            'statuses' => ServiceAppointmentStatus::cases(),
            'counts' => ServiceAppointment::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'shops' => ServiceShop::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    private function query(): Builder
    {
        return ServiceAppointment::query()
            ->when($this->status !== '', fn (Builder $q) => $q->where('status', $this->status))
            ->when($this->shop !== '', fn (Builder $q) => $q->where('service_shop_id', $this->shop));
    }
}
