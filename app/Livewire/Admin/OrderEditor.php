<?php

namespace App\Livewire\Admin;

use App\Models\Order;
use App\Models\ShippingProvider;
use App\Shipping\ShipmentService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::admin')]
class OrderEditor extends Component
{
    public Order $order;
    public ?int $shippingProviderId = null;
    public float $weightKg = 1;
    public int $pieces = 1;

    public function mount(Order $order): void
    {
        $this->order = $order;
        $this->shippingProviderId = ShippingProvider::where('is_active', true)->value('id');
        $this->weightKg = max(1, (float) $order->items()->with('product')->get()->sum(fn ($item) => ((float) $item->product?->weight_kg ?: 1) * $item->quantity));
    }

    public function createAwb(ShipmentService $shipments): void
    {
        $data = $this->validate(['shippingProviderId' => ['required', 'exists:shipping_providers,id'], 'weightKg' => ['required', 'numeric', 'min:0.1'], 'pieces' => ['required', 'integer', 'min:1']]);
        $shipments->createAwb($this->order, ShippingProvider::findOrFail($data['shippingProviderId']), ['weight_kg' => $data['weightKg'], 'pieces' => $data['pieces']]);
        session()->flash('success', 'AWB-ul a fost generat.');
    }

    public function refreshTracking(int $shipmentId, ShipmentService $shipments): void
    {
        $shipments->refreshTracking($this->order->shipments()->findOrFail($shipmentId));
        session()->flash('success', 'Tracking actualizat.');
    }

    public function render()
    {
        return view('livewire.admin.order-editor', [
            'providers' => ShippingProvider::where('is_active', true)->get(),
            'freshOrder' => $this->order->fresh(['items', 'payments.provider', 'shipments.provider', 'shipments.events', 'shippingAddress']),
        ]);
    }
}
