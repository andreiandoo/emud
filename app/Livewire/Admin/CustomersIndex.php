<?php

namespace App\Livewire\Admin;

use App\Models\Order;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::admin')]
class CustomersIndex extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    public ?int $selectedId = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function select(int $userId): void
    {
        $this->selectedId = $userId;
    }

    public function render()
    {
        return view('livewire.admin.customers-index', [
            'customers' => User::query()
                ->where('role', 'customer')
                ->when($this->search !== '', fn ($query) => $query->where(function ($inner): void {
                    $term = '%'.mb_strtolower($this->search).'%';
                    $inner->whereRaw('lower(name) like ?', [$term])->orWhereRaw('lower(email) like ?', [$term]);
                }))
                ->withCount(['vehicles', 'wishlistItems'])
                ->latest('id')
                ->paginate(20),
            'selected' => $this->selectedCustomer(),
            'orders' => $this->selectedId === null
                ? collect()
                : Order::query()->where('user_id', $this->selectedId)->latest('id')->limit(10)->get(),
        ]);
    }

    private function selectedCustomer(): ?User
    {
        return $this->selectedId === null
            ? null
            : User::query()
                ->where('role', 'customer')
                ->with(['vehicles.make', 'vehicles.model'])
                ->find($this->selectedId);
    }
}
