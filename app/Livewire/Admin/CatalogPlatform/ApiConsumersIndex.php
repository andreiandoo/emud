<?php

namespace App\Livewire\Admin\CatalogPlatform;

use App\Models\CatalogApiConsumer;
use App\Models\CatalogApiKey;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::admin')]
class ApiConsumersIndex extends Component
{
    public string $name = '';

    public string $email = '';

    public string $plan = 'basic';

    public int $monthlyQuota = 1000;

    public ?string $issuedToken = null;

    public function createConsumer(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'plan' => ['required', 'in:basic,pro,ultra,mega,enterprise'],
            'monthlyQuota' => ['required', 'integer', 'min:0'],
        ]);

        CatalogApiConsumer::query()->create([
            'public_id' => (string) Str::ulid(),
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']).'-'.Str::lower(Str::random(5)),
            'email' => $validated['email'] ?: null,
            'plan' => $validated['plan'],
            'monthly_quota' => $validated['monthlyQuota'],
            'period_started_at' => now()->startOfMonth(),
        ]);

        $this->reset(['name', 'email']);
        session()->flash('status', 'API consumer creat.');
    }

    public function issueKey(int $consumerId): void
    {
        $consumer = CatalogApiConsumer::query()->findOrFail($consumerId);
        $issued = CatalogApiKey::issue($consumer, 'Admin issued '.now()->format('Y-m-d H:i'));
        $this->issuedToken = $issued['token'];
    }

    public function revokeKey(int $keyId): void
    {
        CatalogApiKey::query()->findOrFail($keyId)->update(['revoked_at' => now()]);
    }

    public function toggleConsumer(int $consumerId): void
    {
        $consumer = CatalogApiConsumer::query()->findOrFail($consumerId);
        $consumer->update(['is_active' => ! $consumer->is_active]);
    }

    public function render()
    {
        return view('livewire.admin.catalog-platform.api-consumers-index', [
            'consumers' => CatalogApiConsumer::query()->with(['keys' => fn ($q) => $q->latest()])->latest()->get(),
        ]);
    }
}
