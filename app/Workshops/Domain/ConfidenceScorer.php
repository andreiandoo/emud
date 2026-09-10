<?php

namespace App\Workshops\Domain;

use App\Models\Workshop;
use App\Workshops\Support\TextNormalizer;

/**
 * One number for "how sure are we this workshop exists, here, as described", 0 to 100.
 *
 * A current RAR authorisation is the strongest single proof of a working workshop. An OpenStreetMap
 * point on its own says someone mapped a workshop once. Independent sources agreeing raise the
 * score; a company ONRC reports as dissolved or struck off lowers it sharply.
 */
class ConfidenceScorer
{
    private const INACTIVE_COMPANY_WORDS = ['radiat', 'radiere', 'dizolv', 'lichid', 'falim', 'insolv', 'suspendare', 'inactiv'];

    public function score(Workshop $workshop): int
    {
        $links = $workshop->sourceLinks()->with(['dataSource:id,key,type', 'sourceRecord:id,is_current'])->get();
        $current = $links->filter(fn ($link): bool => (bool) $link->sourceRecord?->is_current);
        $types = $current->map(fn ($link): ?string => $link->dataSource?->type)->filter()->unique();

        $score = match (true) {
            $types->contains('registry') => 85,
            $types->contains('poi') => 60,
            $types->contains('web') => 50,
            $current->isNotEmpty() => 45,
            $links->isNotEmpty() => 25,
            default => 10,
        };

        if ($types->contains('registry') && $types->contains('poi')) {
            $score += 5;
        }

        if (($workshop->coordinates_confidence ?? 0) >= 60) {
            $score += 5;
        }

        $company = $workshop->company;

        if ($company?->onrc_verified_at !== null) {
            $status = TextNormalizer::fold($company->status);
            $inactive = collect(self::INACTIVE_COMPANY_WORDS)->contains(fn (string $word): bool => str_contains($status, $word));
            $score += $inactive ? -30 : 5;
        }

        return max(0, min(100, $score));
    }
}
