<?php

namespace App\Workshops\Search;

use App\Models\Workshop;
use App\Workshops\Classification\KeywordServiceClassifier;
use App\Workshops\Classification\ServiceTaxonomy;
use App\Workshops\Support\RomanianCounties;
use App\Workshops\Support\TextNormalizer;

/**
 * Reads a plain search like "service cutii automate Brașov", "suspensii offroad București" or
 * "ITP 4x4 Iași" into filters: a county or town, the services named, and a capability where the
 * words name one. Whatever is not understood stays as text to search for.
 */
class QueryInterpreter
{
    /** Words that say "a workshop" and nothing more. */
    private const FILLER = ['service', 'servicii', 'servis', 'atelier', 'ateliere', 'auto', 'reparatii', 'reparatie', 'masini', 'masina', 'in', 'din', 'la', 'de', 'pentru', 'jud', 'judet', 'judetul', 'municipiul', 'orasul', 'si'];

    /** Services that name a capability better than a service row does. */
    private const CAPABILITY_SERVICES = [
        '4x4_drivetrain' => '4x4',
        'electric_vehicle' => 'ev',
        'hybrid' => 'hybrid',
        'truck_service' => 'trucks',
    ];

    private const OFFROAD_SERVICES = ['offroad_suspension', 'offroad_modifications', 'winch_installation', 'snorkel_installation'];

    public function __construct(private KeywordServiceClassifier $classifier) {}

    /** @return array{filters: WorkshopFilters, understood: list<string>} */
    public function interpret(string $input, ?WorkshopFilters $base = null): array
    {
        $filters = $base ?? new WorkshopFilters;
        $understood = [];
        $folded = ' '.TextNormalizer::fold($input).' ';

        foreach (RomanianCounties::ALL as $code => [$name]) {
            $countyWords = TextNormalizer::fold($name);

            if (str_contains($folded, ' '.$countyWords.' ')) {
                $filters->county = $code;
                $understood[] = 'județul '.$name;
                $folded = str_replace(' '.$countyWords.' ', ' ', $folded);

                break;
            }
        }

        $services = $this->classifier->classify($folded);

        if (isset($services['itp'])) {
            $filters->itp = true;
            $understood[] = 'stație ITP';
            unset($services['itp']);

            if (isset($services['4x4_drivetrain'])) {
                $filters->capabilities[] = 'itp_4x4';
                $understood[] = 'poate inspecta 4x4 cu tracțiune permanentă';
                unset($services['4x4_drivetrain']);
            }
        }

        foreach (self::CAPABILITY_SERVICES as $service => $capability) {
            if (isset($services[$service])) {
                $filters->capabilities[] = $capability;
                $understood[] = ServiceTaxonomy::name($service);
                unset($services[$service]);
            }
        }

        $offroad = array_intersect(array_keys($services), self::OFFROAD_SERVICES);

        if ($offroad !== []) {
            $filters->sort = 'offroad';
            $understood[] = 'relevanță off-road';
        }

        // The most specific service named becomes the filter; the rest only explain the order.
        $service = collect($services)->sortByDesc('confidence')->keys()->first();

        if ($service !== null) {
            $filters->service = $service;
            $understood[] = ServiceTaxonomy::name($service);
        }

        $leftover = array_values(array_filter(
            explode(' ', trim($this->withoutMatchedWords($folded, array_keys($services)))),
            fn (string $word): bool => $word !== '' && ! in_array($word, self::FILLER, true),
        ));

        if ($leftover !== []) {
            $locality = implode(' ', $leftover);

            if (Workshop::query()->where('normalized_locality', $locality)->exists()) {
                $filters->locality = $locality;
                $understood[] = 'localitatea '.$locality;
                $leftover = [];
            }
        }

        $filters->capabilities = array_values(array_unique($filters->capabilities));
        $filters->text = $leftover === [] ? null : implode(' ', $leftover);

        return ['filters' => $filters, 'understood' => $understood];
    }

    /** Removes the words that produced the service matches, so they are not searched as names. */
    private function withoutMatchedWords(string $folded, array $services): string
    {
        $words = [
            'cutii', 'cutie', 'viteze', 'viteza', 'automate', 'automata', 'transmisii', 'transmisie', 'reductor', 'transfer',
            'diferentiale', 'diferential', '4x4', 'tractiune', 'integrala', 'suspensii', 'suspensie', 'offroad', 'off', 'road',
            'inaltare', 'inaltari', 'lift', 'kit', 'troliu', 'trolii', 'winch', 'snorkel', 'diagnoza', 'geometrie', 'vulcanizare',
            'anvelope', 'tinichigerie', 'vopsitorie', 'caroserie', 'climatizare', 'frane', 'directie', 'itp', 'gpl', 'gnc',
            'electrice', 'electric', 'hibrid', 'hibride', 'camioane', 'camion', 'injectoare', 'injectie', 'dpf', 'egr', 'tractari',
            'detailing', 'sudura', 'revizii', 'revizie', 'motor', 'motoare', 'ambreiaj', 'esapament', 'parbriz', 'parbrize',
        ];

        return $services === [] ? $folded : ' '.implode(' ', array_diff(explode(' ', trim($folded)), $words)).' ';
    }
}
