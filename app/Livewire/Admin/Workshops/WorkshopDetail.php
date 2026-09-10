<?php

namespace App\Livewire\Admin\Workshops;

use App\Models\Workshop;
use App\Models\WorkshopMatchCandidate;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * One workshop with everything it is built from: the company, the place, every contact with the
 * source and page it came from, services grouped by kind of evidence, RAR authorisations with
 * every activity code as published, and the source records behind all of it.
 */
#[Layout('layouts::admin')]
class WorkshopDetail extends Component
{
    public Workshop $workshop;

    public function mount(Workshop $workshop): void
    {
        $this->workshop = $workshop;
    }

    public function render()
    {
        $this->workshop->load([
            'company',
            'mergedInto:id,name',
            'contacts.dataSource:id,key,name',
            'capabilities',
            'services.serviceType:id,key',
            'authorizations' => fn ($query) => $query->orderByDesc('is_current')->orderBy('system')->orderByDesc('valid_until'),
            'authorizations.activities',
            'sourceLinks.dataSource:id,key,name',
            'sourceLinks.sourceRecord:id,external_id,record_type,is_current,last_seen_at,parse_status',
            'websiteCandidates',
        ]);

        return view('livewire.admin.workshops.detail', [
            'candidates' => WorkshopMatchCandidate::query()
                ->with(['workshopA:id,name,locality', 'workshopB:id,name,locality'])
                ->where(fn ($query) => $query->where('workshop_a_id', $this->workshop->id)->orWhere('workshop_b_id', $this->workshop->id))
                ->latest('id')
                ->limit(20)
                ->get(),
            'merged' => Workshop::query()->where('merged_into_id', $this->workshop->id)->get(['id', 'name', 'locality']),
            'companyWorkshops' => $this->workshop->company?->workshops()->canonical()->count() ?? 0,
            'capabilityLabels' => WorkshopsIndex::CAPABILITIES,
            'systemLabels' => ['SERVICE' => 'Service auto', 'ITP' => 'Stație ITP', 'GPL' => 'GPL / GNC', 'TLV' => 'Tahografe / limitatoare', 'B4' => 'Modificări (B4)'],
        ]);
    }
}
