<?php

namespace App\Workshops\Classification;

use App\Workshops\Contracts\ServiceClassifier;
use App\Workshops\Support\TextNormalizer;

/**
 * The default classifier: Romanian phrases a workshop uses to describe its work, matched on the
 * folded text (lowercase, no diacritics), each mapped to one of our services with a confidence
 * and the snippet it was found in.
 *
 * Deliberately literal. "4x4" on a page is evidence the workshop talks about four-wheel drive;
 * whether it can do the work is for the capability assessor to weigh against everything else.
 */
class KeywordServiceClassifier implements ServiceClassifier
{
    /** @var list<array{0: string, 1: string, 2: int}> [pattern on folded text, service key, confidence] */
    private const RULES = [
        ['cutii? (?:de viteze? )?automate|transmisii? automate?|\bdsg\b|\bcvt\b|convertizor', 'automatic_transmission', 80],
        ['cutii? de viteze?|cutie de viteza manuala|ambreiaj', 'manual_transmission', 60],
        ['reductor(?:ul|ului|ii)?\b|cutii? de transfer|transfer case', 'transfer_case', 85],
        ['diferential(?:e|ul|ului)?\b|punte motoare|blocaj(?:e)? diferential', 'differential', 80],
        ['\b4x4\b|\b4 x 4\b|tractiune integrala|\b4wd\b|\bawd\b', '4x4_drivetrain', 75],
        ['inaltar(?:e|i) (?:suspensie|auto)|suspensi(?:e|i) off ?road|\blift kit|kit(?:uri)? de inaltare|body lift', 'offroad_suspension', 85],
        ['off ?road|\boverland|echipari 4x4|echipamente 4x4|pregatire (?:auto )?expeditie', 'offroad_modifications', 70],
        ['\btroli(?:u|i|ul|uri)\b|\bwinch', 'winch_installation', 85],
        ['\bsnorkel', 'snorkel_installation', 90],
        ['proiectoare|bar(?:a|e) (?:de )?led|light ?bar|faruri suplimentare|lumini suplimentare', 'lighting_installation', 70],
        ['carlig(?:e|ul)? (?:de )?remorcare', 'towbar_installation', 85],
        ['diagnoz(?:a|e)|diagnosticare|tester auto', 'diagnostics', 80],
        ['geometri(?:e|a) (?:roti|rotilor|directie)|\bgeometrie\b|aliniere roti', 'wheel_alignment', 85],
        ['echilibrare', 'wheel_balancing', 80],
        ['vulcanizare|\banvelope\b|\bpneuri\b|\bcauciucuri\b', 'tyres', 80],
        ['tinichigerie|\bcaroserie\b|reparatii caroserie', 'bodywork', 80],
        ['vopsitorie|vopsire auto|vopsitorii', 'painting', 85],
        ['parbriz(?:e)?|geamuri auto', 'glass', 75],
        ['climatizare|aer conditionat|incarcare freon|\bfreon\b|\bclima\b', 'air_conditioning', 80],
        ['\bfrane\b|placute de frana|discuri de frana|sistem(?:ul)? de franare', 'brakes', 75],
        ['caseta de directie|sistem(?:ul)? de directie|\bdirectie\b', 'steering', 70],
        ['\bsuspensi(?:e|i|a)\b|amortizoare|\bbrate\b', 'suspension', 70],
        ['electrica auto|electrician auto|instalati(?:e|a) electrica|electromotor|alternator', 'electrical', 75],
        ['electronica auto|calculatoare auto|\becu\b|chiptuning|remapare', 'electronics', 70],
        ['injectoare|injectie|pompa de injectie|common rail', 'fuel_injection', 80],
        ['\bdiesel\b', 'diesel', 55],
        ['reparatii motor|rectificari|reconditionare motor|\bmotoare\b|\bmotor\b', 'engine', 65],
        ['toba de esapament|\besapament|sistem(?:ul)? de evacuare|\btobe\b', 'exhaust', 70],
        ['\bdpf\b|\bfap\b|filtru de particule', 'dpf', 85],
        ['\begr\b', 'egr', 80],
        ['\bhibrid(?:e)?\b|\bhybrid\b', 'hybrid', 70],
        ['vehicule electrice|masini electrice|autoturisme electrice|\bev\b', 'electric_vehicle', 65],
        ['\bcamioane\b|\bcamion\b|\btir(?:uri)?\b|autocamioane|autobuze', 'truck_service', 70],
        ['autoutilitare|\butilitare\b|\bdube\b|vehicule comerciale', 'commercial_vehicle_service', 60],
        ['\bitp\b|inspecti(?:e|a) tehnica periodica', 'itp', 80],
        ['\bgpl\b|instalati(?:e|i) gpl', 'gpl', 80],
        ['\bgnc\b', 'gnc', 70],
        ['tractari|tractare|platforma auto|asistenta rutiera', 'towing', 80],
        ['detailing|\bpolish|protectie ceramica|cosmetica auto', 'detailing', 75],
        ['\bsudura\b|\bsudare\b|sudor', 'welding', 70],
        ['confectionare|fabricatie|prelucrari metalice|prelucrari mecanice', 'fabrication', 60],
        ['\brevizi(?:e|i|a)\b|schimb (?:de )?ulei|service periodic', 'maintenance', 70],
        ['\badas\b|calibrare camer', 'adas_calibration', 80],
        ['motocicl(?:ete|eta)|\bmoto\b|\batv\b', 'motorcycle_service', 60],
        ['tahograf', 'tachograph', 80],
        ['dezmembrari', 'dismantling', 80],
        ['montaj accesorii|accesorii auto', 'accessories_installation', 60],
        ['anticoroziv|antifonare|tratament(?:e)? sub caroserie', 'rustproofing', 80],
    ];

    public function classify(string $text): array
    {
        $folded = TextNormalizer::fold($text);

        if ($folded === '') {
            return [];
        }

        $found = [];

        foreach (self::RULES as [$pattern, $service, $confidence]) {
            if (preg_match_all('/'.$pattern.'/', $folded, $matches, PREG_OFFSET_CAPTURE) === 0) {
                continue;
            }

            $snippets = [];

            foreach (array_slice($matches[0], 0, 3) as [$match, $offset]) {
                $start = max(0, $offset - 50);
                $snippets[] = trim(substr($folded, $start, strlen($match) + 100));
            }

            // Mentioned once in passing is weaker than a service the page keeps returning to.
            $confidence = min(95, $confidence + (count($matches[0]) >= 3 ? 5 : 0));

            if ($confidence > ($found[$service]['confidence'] ?? 0)) {
                $found[$service] = ['confidence' => $confidence, 'snippets' => $snippets];
            }
        }

        return $found;
    }
}
