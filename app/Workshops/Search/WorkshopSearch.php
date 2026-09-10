<?php

namespace App\Workshops\Search;

use App\Models\Workshop;
use App\Models\WorkshopDataSource;
use App\Workshops\Classification\ServiceTaxonomy;
use App\Workshops\Support\Geo;
use App\Workshops\Support\Identifiers;
use App\Workshops\Support\RomanianCounties;
use App\Workshops\Support\TextNormalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The registry's one query builder. On PostgreSQL names and addresses are also matched by
 * trigram similarity (pg_trgm), so "Autobela" finds "AB AUTOBELLA"; elsewhere it is plain LIKE.
 * Distance uses a bounding box everywhere and exact great-circle distance on PostgreSQL.
 */
class WorkshopSearch
{
    public function query(WorkshopFilters $filters): Builder
    {
        $query = Workshop::query()->canonical();
        $pgsql = DB::connection()->getDriverName() === 'pgsql';

        $query->when($filters->active !== null, fn (Builder $q) => $q->where('is_active', $filters->active));

        if ($filters->text !== null && ($folded = TextNormalizer::fold($filters->text)) !== '') {
            $digits = preg_replace('/\D+/', '', $filters->text) ?? '';
            $cui = Identifiers::cui($filters->text);

            $query->where(function (Builder $q) use ($folded, $digits, $cui, $pgsql, $filters): void {
                $q->where('normalized_name', 'like', "%{$folded}%")
                    ->orWhere('normalized_address', 'like', "%{$folded}%")
                    ->orWhereHas('company', fn (Builder $company) => $company->where('normalized_name', 'like', "%{$folded}%")
                        ->when($cui !== null && strlen($digits) === strlen((string) $cui), fn (Builder $c) => $c->orWhere('cui', $cui))
                        ->orWhere('registration_number', strtoupper(preg_replace('/\s+/', '', $filters->text) ?? '')));

                if (strlen($digits) >= 6) {
                    $q->orWhereHas('contacts', fn (Builder $contact) => $contact->whereIn('type', ['phone', 'mobile', 'whatsapp'])->where('normalized_value', 'like', '%'.substr($digits, -9).'%'));
                }

                if ($pgsql && strlen($folded) >= 4) {
                    $q->orWhereRaw('normalized_name % ?', [$folded]);
                }
            });
        }

        if ($filters->county !== null && ($county = RomanianCounties::resolve($filters->county)) !== null) {
            $query->where('county_code', $county);
        }

        if ($filters->locality !== null && ($locality = TextNormalizer::fold($filters->locality)) !== '') {
            $query->where(fn (Builder $q) => $q->where('normalized_locality', $locality)->orWhere('normalized_locality', 'like', $locality.' %'));
        }

        foreach (['rar' => 'is_rar_authorized', 'itp' => 'is_itp', 'gplGnc' => 'is_gpl_gnc', 'tlv' => 'is_tlv', 'modifications' => 'is_modification_authorized'] as $property => $column) {
            $query->when($filters->{$property} !== null, fn (Builder $q) => $q->where($column, $filters->{$property}));
        }

        if ($filters->service !== null && ServiceTaxonomy::exists($filters->service)) {
            $keys = [$filters->service, ...array_keys(array_filter(ServiceTaxonomy::DEFINITIONS, fn (array $definition): bool => $definition[1] === $filters->service))];
            $query->whereHas('services', fn (Builder $service) => $service
                ->whereHas('serviceType', fn (Builder $type) => $type->whereIn('key', $keys))
                ->when($filters->evidence, fn (Builder $s, string $evidence) => $s->where('evidence_type', $evidence)));
        }

        if ($filters->authorizationCode !== null) {
            $code = strtoupper(str_replace('.', '_', trim($filters->authorizationCode)));
            $query->whereHas('authorizations', fn (Builder $authorization) => $authorization
                ->where('is_current', true)
                ->whereHas('activities', fn (Builder $activity) => $activity->where('code', $code)->orWhere('code', 'like', $code.'_%')));
        }

        foreach ($filters->capabilities as $capability) {
            match ($capability) {
                '4x4' => $query->where('supports_4x4', true),
                'ev' => $query->where('supports_ev', true),
                'hybrid' => $query->where('supports_hybrid', true),
                'trucks' => $query->where('supports_trucks', true),
                default => $query->whereHas('capabilities', fn (Builder $c) => $c->where('capability', $capability)->where('value', true)),
            };
        }

        foreach (['hasPhone' => ['phone', 'mobile'], 'hasEmail' => ['email'], 'hasWebsite' => ['website']] as $property => $types) {
            if ($filters->{$property} !== null) {
                $exists = fn ($sub) => $sub->select(DB::raw(1))->from('workshop_contacts')->whereColumn('workshop_contacts.workshop_id', 'workshops.id')->whereIn('type', $types);
                $filters->{$property} ? $query->whereExists($exists) : $query->whereNotExists($exists);
            }
        }

        if ($filters->hasCoordinates !== null) {
            $filters->hasCoordinates ? $query->whereNotNull('latitude') : $query->whereNull('latitude');
        }

        if ($filters->source !== null) {
            $sourceId = WorkshopDataSource::query()->where('key', $filters->source)->value('id') ?? 0;
            $query->whereHas('sourceLinks', fn (Builder $link) => $link->where('data_source_id', $sourceId));
        }

        $query->when($filters->minConfidence !== null, fn (Builder $q) => $q->where('confidence_score', '>=', $filters->minConfidence));

        if ($filters->latitude !== null && $filters->longitude !== null && $filters->radiusKm !== null) {
            $box = Geo::boundingBox($filters->latitude, $filters->longitude, $filters->radiusKm * 1000);
            $query->whereBetween('latitude', [$box['min_lat'], $box['max_lat']])->whereBetween('longitude', [$box['min_lng'], $box['max_lng']]);

            if ($pgsql) {
                $distance = '(6371 * acos(least(1, cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))))';
                $bindings = [$filters->latitude, $filters->longitude, $filters->latitude];
                $query->whereRaw("{$distance} <= ?", [...$bindings, $filters->radiusKm]);

                if ($filters->sort === 'distance') {
                    $query->orderByRaw("{$distance} asc", $bindings);
                }
            }
        }

        return match ($filters->sort) {
            'confidence' => $query->orderByDesc('confidence_score')->orderBy('name'),
            'offroad' => $query->orderByRaw('coalesce(offroad_score, 0) desc')->orderByDesc('confidence_score')->orderBy('name'),
            'recent' => $query->orderByDesc('updated_at'),
            'distance' => $query->orderBy('name'),
            default => $query->orderBy('name')->orderBy('id'),
        };
    }
}
