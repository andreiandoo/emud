<?php

namespace App\Http\Controllers\Api\V1;

use App\Catalog\Api\CatalogSerializer;
use App\Catalog\Normalization\IdentifierNormalizer;
use App\Http\Controllers\Controller;
use App\Models\CatalogPartNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartByNumberController extends Controller
{
    public function __invoke(string $number, Request $request, IdentifierNormalizer $normalizer, CatalogSerializer $serializer): JsonResponse
    {
        $compact = $normalizer->compact($number);
        $query = CatalogPartNumber::query()
            ->with(['part.brand', 'part.category', 'part.numbers.brand', 'part.numbers.oeMake'])
            ->whereHas('source', fn ($q) => $q->where('allow_api_redistribution', true))
            ->where(fn ($q) => $q->where('number_compact', $compact)->orWhere('number_normalized', $normalizer->normalize($number)));

        if ($request->filled('scheme')) {
            $query->where('scheme', strtoupper((string) $request->query('scheme')));
        }

        $parts = $query->limit(100)->get()->pluck('part')->filter()->unique('id')->values()->map(fn ($part) => $serializer->part($part));

        return response()->json(['data' => $parts]);
    }
}
