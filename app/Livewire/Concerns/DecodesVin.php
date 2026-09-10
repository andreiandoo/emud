<?php

namespace App\Livewire\Concerns;

use App\Catalog\Vehicles\Vin\VpicVinResolver;
use App\Models\VehicleConfiguration;
use App\Storefront\SelectedVehicle;
use App\Storefront\VehicleContext;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Finding the car from its chassis number.
 *
 * Shared by every widget that offers it — the header's vehicle panel and the finder on a
 * collection page — so the validation, the throttle and the wording of each kind of miss are
 * written once. The using component decides what "selecting" means for it through
 * selectVehicle(): the collection finder closes its dialog, the header re-syncs its dropdowns.
 */
trait DecodesVin
{
    public string $vin = '';

    public string $vinMessage = '';

    /** @var list<array<string, mixed>> */
    public array $vinCandidates = [];

    abstract protected function selectVehicle(VehicleContext $context, SelectedVehicle $vehicle): void;

    public function decodeVin(VpicVinResolver $resolver, VehicleContext $context): void
    {
        // I, O and Q are not VIN characters — they are excluded precisely because they are
        // mistaken for 1 and 0 — so a VIN carrying one is a typo, not a lookup.
        $this->validate([
            'vin' => ['required', 'string', 'regex:/^[A-HJ-NPR-Z0-9]{17}$/i'],
        ], [
            'vin.required' => 'Scrie seria de șasiu.',
            'vin.regex' => 'Seria de șasiu are 17 caractere și nu conține literele I, O sau Q.',
        ]);

        // Each decode is an outbound call to vPIC. Throttled per visitor so a script cannot use
        // the shop as a free VIN decoding service.
        $key = 'vin-decode:'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 15)) {
            throw ValidationException::withMessages([
                'vin' => 'Prea multe căutări. Încearcă din nou peste '.RateLimiter::availableIn($key).' secunde.',
            ]);
        }

        RateLimiter::hit($key, 3600);

        $this->vinCandidates = [];
        $this->vinMessage = '';

        $result = $resolver->resolve($this->vin);

        if ($result->vehicleConfigurationId !== null) {
            $this->selectConfiguration($context, $result->vehicleConfigurationId);

            return;
        }

        if ($result->candidates !== []) {
            $this->vinCandidates = $result->candidates;
            $this->vinMessage = 'Am găsit mai multe variante pentru seria asta. Alege-o pe a ta.';

            return;
        }

        // Everything else is a miss of some kind, and they are worth telling apart: a decoder
        // that is down is a different problem for the customer than a car we simply do not have.
        $this->vinMessage = match ($result->status) {
            'invalid' => 'Seria de șasiu nu pare validă.',
            'unsupported', 'unavailable' => 'Căutarea după serie nu este disponibilă acum. Alege mașina din listă.',
            default => $this->decodedLabel($result->decoded) === null
                ? 'Nu am putut identifica mașina după serie. Alege-o din listă.'
                : 'Am decodat '.$this->decodedLabel($result->decoded).', dar nu avem încă mașina asta în catalog.',
        };
    }

    public function chooseCandidate(int $configurationId, VehicleContext $context): void
    {
        $this->selectConfiguration($context, $configurationId);
    }

    /**
     * Selecting from a configuration rather than from three dropdowns is what makes a VIN worth
     * decoding: it carries the year and the exact build, which a person picking from lists
     * usually cannot supply.
     */
    private function selectConfiguration(VehicleContext $context, int $configurationId): void
    {
        $configuration = VehicleConfiguration::query()
            ->with('generation.model.make')
            ->find($configurationId);

        $model = $configuration?->generation?->model;

        if ($model?->make === null) {
            $this->vinMessage = 'Am identificat mașina, dar lipsesc datele ei din catalog.';

            return;
        }

        $this->vinCandidates = [];
        $this->vinMessage = '';

        $this->selectVehicle($context, new SelectedVehicle(
            makeId: (int) $model->make->id,
            makeName: (string) $model->make->name,
            modelId: (int) $model->id,
            modelName: (string) $model->name,
            generationId: $configuration->generation?->id,
            generationName: $configuration->generation?->name,
            configurationId: (int) $configuration->id,
            year: $configuration->year ? (int) $configuration->year : null,
        ));
    }

    /** @param array<string, mixed> $decoded */
    private function decodedLabel(array $decoded): ?string
    {
        $label = trim(implode(' ', array_filter([
            $decoded['ModelYear'] ?? null,
            $decoded['Make'] ?? null,
            $decoded['Model'] ?? null,
        ])));

        return $label === '' ? null : $label;
    }
}
