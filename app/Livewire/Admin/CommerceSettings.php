<?php

namespace App\Livewire\Admin;

use App\Models\PaymentProvider;
use App\Models\ShippingMethod;
use App\Models\ShippingProvider;
use Database\Seeders\CommerceProviderSeeder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::admin')]
class CommerceSettings extends Component
{
    public array $payments = [];
    public array $shippingProviders = [];
    public array $shippingMethods = [];

    public function mount(): void
    {
        if (PaymentProvider::count() === 0) app(CommerceProviderSeeder::class)->run();
        $this->loadSettings();
    }

    public function save(): void
    {
        $this->validate([
            'payments.*.mode' => ['required', 'in:sandbox,live'],
            'shippingProviders.*.mode' => ['required', 'in:sandbox,live'],
            'shippingMethods.*.name' => ['required', 'string', 'max:255'],
            'shippingMethods.*.base_price' => ['required', 'numeric', 'min:0'],
            'shippingMethods.*.free_over' => ['nullable', 'numeric', 'min:0'],
        ]);

        DB::transaction(function (): void {
            $defaultId = collect($this->payments)->firstWhere('is_default', true)['id'] ?? null;
            foreach ($this->payments as $row) {
                $provider = PaymentProvider::findOrFail($row['id']);
                $credentials = array_filter($row['credentials'], fn ($value) => $value !== '' && $value !== null);
                $provider->update([
                    'mode' => $row['mode'], 'is_active' => (bool) $row['is_active'],
                    'is_default' => $provider->id === (int) $defaultId,
                    'credentials' => [...($provider->credentials ?? []), ...$credentials],
                    'settings' => $row['settings'] ?? [],
                ]);
            }
            foreach ($this->shippingProviders as $row) {
                $provider = ShippingProvider::findOrFail($row['id']);
                $credentials = array_filter($row['credentials'], fn ($value) => $value !== '' && $value !== null);
                $provider->update([
                    'mode' => $row['mode'], 'is_active' => (bool) $row['is_active'],
                    'credentials' => [...($provider->credentials ?? []), ...$credentials],
                    'settings' => $row['settings'] ?? [],
                ]);
            }
            foreach ($this->shippingMethods as $row) {
                ShippingMethod::findOrFail($row['id'])->update([
                    'name' => $row['name'], 'base_price' => $row['base_price'],
                    'free_over' => $row['free_over'] ?: null, 'is_active' => (bool) $row['is_active'],
                    'estimated_days_min' => $row['estimated_days_min'] ?: null,
                    'estimated_days_max' => $row['estimated_days_max'] ?: null,
                ]);
            }
        });

        $this->loadSettings();
        session()->flash('success', 'Setările comerciale au fost salvate.');
    }

    public function setDefaultPayment(int $index): void
    {
        foreach ($this->payments as $key => $payment) {
            $this->payments[$key]['is_default'] = $key === $index;
        }
    }

    private function loadSettings(): void
    {
        $this->payments = PaymentProvider::orderBy('position')->get()->map(fn ($provider): array => [
            'id' => $provider->id, 'code' => $provider->code, 'name' => $provider->name,
            'mode' => $provider->mode, 'is_active' => $provider->is_active, 'is_default' => $provider->is_default,
            'credentials' => $provider->driver === 'stripe'
                ? ['secret_key' => '', 'publishable_key' => '', 'webhook_secret' => '']
                : ['api_key' => '', 'pos_signature' => ''],
            'configured' => filled($provider->credentials), 'settings' => $provider->settings ?? [],
        ])->all();
        $this->shippingProviders = ShippingProvider::get()->map(fn ($provider): array => [
            'id' => $provider->id, 'name' => $provider->name, 'mode' => $provider->mode,
            'is_active' => $provider->is_active, 'credentials' => ['token' => '', 'client_id' => ''],
            'configured' => filled($provider->credentials), 'settings' => $provider->settings ?? [],
        ])->all();
        $this->shippingMethods = ShippingMethod::orderBy('position')->get()->toArray();
    }

    public function render() { return view('livewire.admin.commerce-settings'); }
}
