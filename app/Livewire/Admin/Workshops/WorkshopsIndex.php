<?php

namespace App\Livewire\Admin\Workshops;

use App\Models\WorkshopDataSource;
use App\Workshops\Classification\ServiceTaxonomy;
use App\Workshops\Export\WorkshopExporter;
use App\Workshops\Search\QueryInterpreter;
use App\Workshops\Search\WorkshopFilters;
use App\Workshops\Search\WorkshopSearch;
use App\Workshops\Support\RomanianCounties;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The national registry as a searchable list. The search box reads plain phrases
 * ("ITP 4x4 Iași", "service cutii automate Brașov") and says what it understood; the filters
 * beside it narrow further, and the export takes exactly what is on screen.
 */
#[Layout('layouts::admin')]
class WorkshopsIndex extends Component
{
    use WithPagination;

    public const CAPABILITIES = [
        '4x4' => '4x4 (mai multe axe)',
        'awd_permanent' => 'Tracțiune integrală permanentă',
        'offroad' => 'Specialist off-road',
        'ev' => 'Vehicule electrice',
        'hybrid' => 'Vehicule hibride',
        'trucks' => 'Camioane / autobuze',
        'itp_4x4' => 'ITP pentru 4x4 permanent',
    ];

    #[Url(except: '')]
    public string $q = '';

    #[Url(except: '')]
    public string $county = '';

    #[Url(except: '')]
    public string $city = '';

    #[Url(except: '')]
    public string $service = '';

    #[Url(except: '')]
    public string $evidence = '';

    #[Url(except: '')]
    public string $code = '';

    #[Url(except: '')]
    public string $capability = '';

    #[Url(except: '')]
    public string $rar = '';

    #[Url(except: '')]
    public string $itp = '';

    #[Url(except: '')]
    public string $gpl = '';

    #[Url(except: '')]
    public string $contact = '';

    #[Url(except: '')]
    public string $coordinates = '';

    #[Url(except: '')]
    public string $source = '';

    #[Url(except: '')]
    public string $confidence = '';

    #[Url(except: 'active')]
    public string $state = 'active';

    #[Url(except: 'name')]
    public string $sort = 'name';

    public function updated(string $property): void
    {
        if ($property !== 'page') {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset(['q', 'county', 'city', 'service', 'evidence', 'code', 'capability', 'rar', 'itp', 'gpl', 'contact', 'coordinates', 'source', 'confidence', 'state', 'sort']);
        $this->resetPage();
    }

    public function export(): BinaryFileResponse
    {
        $path = storage_path('app/private/workshops/exports/admin-'.now()->format('Ymd-His').'-'.uniqid().'.csv');

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }

        app(WorkshopExporter::class)->export($this->filters()['filters'], 'csv', $path);

        return response()->download($path, 'ateliere-'.now()->format('Y-m-d').'.csv')->deleteFileAfterSend();
    }

    public function render()
    {
        ['filters' => $filters, 'understood' => $understood] = $this->filters();

        $workshops = app(WorkshopSearch::class)->query($filters)
            ->with(['company:id,legal_name,cui,status', 'contacts:id,workshop_id,type', 'capabilities:id,workshop_id,capability,value,score'])
            ->paginate(50);

        return view('livewire.admin.workshops.index', [
            'workshops' => $workshops,
            'understood' => $understood,
            'counties' => RomanianCounties::ALL,
            'services' => ServiceTaxonomy::DEFINITIONS,
            'capabilities' => self::CAPABILITIES,
            'sources' => WorkshopDataSource::query()->orderBy('key')->pluck('name', 'key'),
        ]);
    }

    /** @return array{filters: WorkshopFilters, understood: list<string>} */
    private function filters(): array
    {
        $filters = WorkshopFilters::fromArray([
            'county' => $this->county,
            'city' => $this->city,
            'service' => $this->service,
            'evidence' => $this->evidence,
            'code' => $this->code,
            'capability' => $this->capability,
            'rar' => $this->rar,
            'itp' => $this->itp,
            'gpl' => $this->gpl,
            'has_phone' => $this->contact === 'phone' ? '1' : null,
            'has_email' => $this->contact === 'email' ? '1' : null,
            'has_website' => $this->contact === 'website' ? '1' : null,
            'has_coordinates' => $this->coordinates,
            'source' => $this->source,
            'min_confidence' => $this->confidence,
            'sort' => $this->sort,
        ]);

        $filters->active = match ($this->state) {
            'inactive' => false,
            'all' => null,
            default => true,
        };

        if ($this->contact === 'none') {
            $filters->hasPhone = false;
            $filters->hasEmail = false;
        }

        if (trim($this->q) === '') {
            return ['filters' => $filters, 'understood' => []];
        }

        return app(QueryInterpreter::class)->interpret($this->q, $filters);
    }
}
