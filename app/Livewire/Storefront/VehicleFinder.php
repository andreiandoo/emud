<?php

namespace App\Livewire\Storefront;

use App\Catalog\Vehicles\Vin\VpicVinResolver;
use App\Models\VehicleCollection;
use App\Models\VehicleConfiguration;
use App\Models\VehicleGeneration;
use App\Models\VehicleMake;
use App\Models\VehicleModel;
use App\Storefront\SelectedVehicle;
use App\Storefront\VehicleContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * The three ways into a collection: pick the car, decode a VIN, or choose a derivative.
 *
 * One component rather than three because they answer the same question and the customer should
 * be able to give up on one and try the next without the page moving underneath them. The
 * make is taken from the collection being viewed, so someone already looking at Dacia is not
 * asked which manufacturer they meant.
 */
class VehicleFinder extends Component
{
    public int $collectionId;

    /** Which panel is open, mirrored from Alpine so a server round trip does not close it. */
    public string $panel = '';

    public string $pickYear = '';

    public string $pickMakeId = '';

    public string $pickModelId = '';

    public string $pickGenerationId = '';

    public string $vin = '';

    public string $vinMessage = '';

    /** @var list<array<string, mixed>> */
    public array $vinCandidates = [];

    public string $applied = '';

    public function mount(VehicleCollection $collection): void
    {
        $this->collectionId = (int) $collection->id;
        $this->pickMakeId = (string) ($collection->make_id ?? '');
        $this->pickModelId = (string) ($collection->model_id ?? '');
    }

    public function updatedPickMakeId(): void
    {
        $this->reset(['pickModelId', 'pickGenerationId']);
    }

    public function updatedPickModelId(): void
    {
        $this->pickGenerationId = '';
    }

    /** A different year can invalidate the model and the submodel under it. */
    public function updatedPickYear(): void
    {
        if ($this->pickModelId !== '' && ! $this->models()->contains('id', (int) $this->pickModelId)) {
            $this->reset(['pickModelId', 'pickGenerationId']);
        }

        if ($this->pickGenerationId !== '' && ! $this->generations()->contains('id', (int) $this->pickGenerationId)) {
            $this->pickGenerationId = '';
        }
    }

    /**
     * The submodel is optional. Plenty of owners know the make, the model and the year and have
     * no idea which generation that makes them, and refusing the selection until they do would
     * strand exactly the people this exists for.
     */
    public function applyPick(VehicleContext $context): void
    {
        // Re-checked here because the ids arrive from the client: a model that does not belong
        // to the chosen make would otherwise be stored and filter for the wrong car.
        $this->validate([
            'pickMakeId' => ['required', 'integer', 'exists:vehicle_makes,id'],
            'pickModelId' => ['required', 'integer', Rule::exists('vehicle_models', 'id')->where('make_id', $this->pickMakeId)],
            'pickGenerationId' => ['nullable', 'integer', Rule::exists('vehicle_generations', 'id')->where('model_id', $this->pickModelId)],
            'pickYear' => ['nullable', 'integer', 'min:1900', 'max:2100'],
        ], [
            'pickMakeId.required' => 'Alege producătorul.',
            'pickModelId.required' => 'Alege modelul.',
            'pickModelId.exists' => 'Modelul ales nu aparține acestui producător.',
            'pickGenerationId.exists' => 'Submodelul ales nu aparține acestui model.',
        ]);

        $make = VehicleMake::query()->findOrFail($this->pickMakeId);
        $model = VehicleModel::query()->findOrFail($this->pickModelId);
        $generation = $this->pickGenerationId === '' ? null : VehicleGeneration::query()->find($this->pickGenerationId);

        $this->select($context, new SelectedVehicle(
            makeId: (int) $make->id,
            makeName: (string) $make->name,
            modelId: (int) $model->id,
            modelName: (string) $model->name,
            generationId: $generation?->id,
            generationName: $generation?->name,
            year: $this->pickYear === '' ? null : (int) $this->pickYear,
        ));
    }

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

    public function render()
    {
        return view('livewire.storefront.vehicle-finder', [
            'collection' => VehicleCollection::query()->findOrFail($this->collectionId),
            'makes' => $this->makes(),
            'years' => $this->years(),
            'models' => $this->models(),
            'generations' => $this->generations(),
            'children' => $this->children(),
        ]);
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

        $this->select($context, new SelectedVehicle(
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

    private function select(VehicleContext $context, SelectedVehicle $vehicle): void
    {
        $context->select($vehicle);

        $this->applied = $vehicle->label();
        $this->vinCandidates = [];
        $this->vinMessage = '';

        // Two events: one the rest of the page already listens for, one the panel closes on.
        $this->dispatch('vehicle-changed');
        $this->dispatch('vehicle-picked');
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

    /**
     * Proving that a make has a real vehicle behind it means walking its models and generations,
     * and this list is identical for every visitor. An hour of staleness costs nothing; the scan
     * on every panel open would.
     *
     * @return EloquentCollection<int, VehicleMake>
     */
    private function makes(): EloquentCollection
    {
        return Cache::remember(
            'storefront:vehicle-picker:makes',
            now()->addHour(),
            fn (): EloquentCollection => VehicleMake::query()
                ->where('is_active', true)
                ->withConfigurations()
                ->orderBy('name')
                ->get(['id', 'name']),
        );
    }

    /** @return list<int> */
    private function years(): array
    {
        if ($this->pickMakeId === '') {
            return [];
        }

        return Cache::remember(
            'storefront:finder:years:'.$this->pickMakeId,
            now()->addHour(),
            fn (): array => VehicleConfiguration::query()
                ->whereNotNull('year')
                ->whereHas('generation.model', fn (Builder $q) => $q->where('make_id', $this->pickMakeId))
                ->distinct()
                ->orderByDesc('year')
                ->pluck('year')
                ->map(fn ($year): int => (int) $year)
                ->all(),
        );
    }

    /** @return EloquentCollection<int, VehicleModel> */
    private function models(): EloquentCollection
    {
        if ($this->pickMakeId === '') {
            return new EloquentCollection;
        }

        return VehicleModel::query()
            ->where('make_id', $this->pickMakeId)
            ->when(
                $this->pickYear !== '',
                fn (Builder $q) => $q->whereHas('generations.configurations', fn (Builder $c) => $c->where('year', $this->pickYear)),
                fn (Builder $q) => $q->withConfigurations(),
            )
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /** @return EloquentCollection<int, VehicleGeneration> */
    private function generations(): EloquentCollection
    {
        if ($this->pickModelId === '') {
            return new EloquentCollection;
        }

        return VehicleGeneration::query()
            ->where('model_id', $this->pickModelId)
            ->when($this->pickYear !== '', fn (Builder $q) => $q
                ->whereHas('configurations', fn (Builder $c) => $c->where('year', $this->pickYear)))
            ->orderByDesc('year_from')
            ->get(['id', 'name', 'year_from', 'year_to']);
    }

    /**
     * The derivatives under this collection. Empty on a derivative's own page, where the third
     * button has nothing to offer and is not drawn.
     *
     * @return EloquentCollection<int, VehicleCollection>
     */
    private function children(): EloquentCollection
    {
        return VehicleCollection::query()
            ->where('parent_id', $this->collectionId)
            ->where('is_active', true)
            ->orderBy('position')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'square_image_path', 'year_from', 'year_to']);
    }
}
