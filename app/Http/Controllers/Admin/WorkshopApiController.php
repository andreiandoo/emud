<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Workshop;
use App\Models\WorkshopService;
use App\Workshops\Classification\ServiceTaxonomy;
use App\Workshops\Search\QueryInterpreter;
use App\Workshops\Search\WorkshopFilters;
use App\Workshops\Search\WorkshopSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The registry as JSON for back-office tools: search, one workshop, the service vocabulary.
 * Behind the admin login. What each value rests on is included; raw source content is not.
 */
class WorkshopApiController extends Controller
{
    public function index(Request $request, WorkshopSearch $search, QueryInterpreter $interpreter): JsonResponse
    {
        $filters = WorkshopFilters::fromArray($request->query());
        $understood = [];

        if ($filters->text !== null && $request->boolean('interpret', true)) {
            ['filters' => $filters, 'understood' => $understood] = $interpreter->interpret($filters->text, $filters);
        }

        $page = $search->query($filters)
            ->with(['company:id,legal_name,cui,status', 'contacts:id,workshop_id,type,value,is_primary,confidence_score', 'capabilities:id,workshop_id,capability,value,score'])
            ->paginate(max(1, min(100, (int) $request->query('per_page', 25))));

        return response()->json([
            'data' => collect($page->items())->map(fn (Workshop $workshop): array => $this->summary($workshop))->all(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
                'understood' => $understood,
            ],
        ]);
    }

    public function show(Workshop $workshop): JsonResponse
    {
        $workshop->load([
            'company', 'contacts.dataSource:id,key', 'capabilities', 'services.serviceType:id,key',
            'authorizations' => fn ($query) => $query->where('is_current', true),
            'authorizations.activities', 'sourceLinks.dataSource:id,key',
        ]);

        return response()->json(['data' => $this->summary($workshop) + [
            'company' => $workshop->company?->only(['legal_name', 'cui', 'registration_number', 'status', 'registered_address', 'caen_codes', 'onrc_verified_at']),
            'contacts' => $workshop->contacts->map(fn ($contact): array => [
                'type' => $contact->type, 'value' => $contact->value, 'label' => $contact->label, 'source' => $contact->dataSource?->key,
                'source_url' => $contact->source_url, 'confidence' => $contact->confidence_score, 'primary' => $contact->is_primary,
                'last_seen_at' => $contact->last_seen_at?->toIso8601String(),
            ])->values(),
            'services' => $workshop->services->map(fn (WorkshopService $service): array => [
                'key' => $service->serviceType?->key, 'name' => ServiceTaxonomy::name((string) $service->serviceType?->key),
                'evidence_type' => $service->evidence_type->value, 'authorized' => $service->is_authorized, 'confidence' => $service->confidence_score,
                'evidence' => $service->evidence,
            ])->values(),
            'capabilities' => $workshop->capabilities->map(fn ($capability): array => $capability->only(['capability', 'value', 'score', 'basis', 'evidence']))->values(),
            'authorizations' => $workshop->authorizations->map(fn ($authorization): array => [
                'system' => $authorization->system, 'number' => $authorization->authorization_number, 'exit_number' => $authorization->exit_number,
                'audit_file' => $authorization->audit_file_number, 'station_code' => $authorization->station_code, 'class' => $authorization->authorization_class,
                'valid_until' => $authorization->valid_until?->toDateString(),
                'activities' => $authorization->activities->map(fn ($activity): array => $activity->only(['code', 'display_code', 'parent_code', 'description', 'vehicle_categories', 'limitations', 'restrictions']))->values(),
            ])->values(),
            'sources' => $workshop->sourceLinks->map(fn ($link): array => ['source' => $link->dataSource?->key, 'external_id' => $link->external_id, 'match_type' => $link->match_type, 'confidence' => $link->match_confidence])->values(),
        ]]);
    }

    public function services(): JsonResponse
    {
        $counts = WorkshopService::query()
            ->toBase()
            ->join('workshop_service_types', 'workshop_service_types.id', '=', 'workshop_services.service_type_id')
            ->selectRaw('workshop_service_types.key as service_key, workshop_services.evidence_type, count(distinct workshop_services.workshop_id) as workshops')
            ->groupBy('workshop_service_types.key', 'workshop_services.evidence_type')
            ->get()
            ->groupBy('service_key');

        return response()->json(['data' => collect(ServiceTaxonomy::DEFINITIONS)->map(fn (array $definition, string $key): array => [
            'key' => $key,
            'name' => $definition[0],
            'parent' => $definition[1],
            'workshops' => $counts->get($key, collect())->mapWithKeys(fn ($row): array => [$row->evidence_type => (int) $row->workshops]),
        ])->values()]);
    }

    private function summary(Workshop $workshop): array
    {
        return [
            'id' => $workshop->id,
            'name' => $workshop->name,
            'company' => $workshop->company?->legal_name,
            'cui' => $workshop->company?->cui,
            'address' => $workshop->address,
            'locality' => $workshop->locality,
            'county_code' => $workshop->county_code,
            'latitude' => $workshop->latitude,
            'longitude' => $workshop->longitude,
            'coordinates_confidence' => $workshop->coordinates_confidence,
            'phone' => $workshop->primaryContact('phone', 'mobile')?->value,
            'email' => $workshop->primaryContact('email')?->value,
            'website' => $workshop->primaryContact('website')?->value,
            'rar_authorized' => $workshop->is_rar_authorized,
            'itp' => $workshop->is_itp,
            'gpl_gnc' => $workshop->is_gpl_gnc,
            'supports_4x4' => $workshop->supports_4x4,
            'supports_ev' => $workshop->supports_ev,
            'supports_hybrid' => $workshop->supports_hybrid,
            'supports_trucks' => $workshop->supports_trucks,
            'offroad_score' => $workshop->offroad_score,
            'confidence' => $workshop->confidence_score,
            'active' => $workshop->is_active,
        ];
    }
}
