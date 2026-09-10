<?php

namespace App\Workshops\Sources\Rar;

use App\Workshops\Data\CompanyData;
use App\Workshops\Data\LocationData;
use App\Workshops\Data\PhoneNumber;
use App\Workshops\Support\AddressNormalizer;
use App\Workshops\Support\Geo;
use App\Workshops\Support\Identifiers;
use App\Workshops\Support\PhoneNormalizer;
use App\Workshops\Support\RomanianCounties;
use App\Workshops\Support\TextNormalizer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Throwable;

/**
 * Reads one RAR authorisation as the public registry returns it.
 *
 * Tolerant by design: every field may be missing, repeated, typed in capitals or with cedilla
 * letters, and a phone field may hold two numbers. What cannot be read is left null rather than
 * guessed. Activity codes and their wording are kept exactly as RAR publishes them; mapping them
 * to our own services happens elsewhere (RarActivityMap), so this stays a faithful reading.
 *
 * What the coordinates are worth is decided here, from what was seen in the data: most points
 * carry two decimals (a town, not a gate), thousands repeat the registered office's point even
 * when the workshop is elsewhere, and a few put a Brașov workshop in Bucharest.
 */
class RarAuthorizationParser
{
    private const HIERARCHICAL = '/^[AB]\d+(?:_\d+)*$/';

    /** The keys that hold activities, per section; everything else is summary. */
    private const ACTIVITY_KEYS = [
        'authorizedActivities', 'authorizedActivitiesGpl', 'authorizationB4ActivitiesDetails',
        'authorizationTlvActivitiesDetails', 'evaluationTlvActivitiesDetails', 'itpAuthClassDetails',
    ];

    public function __construct(private RarNomenclature $nomenclature) {}

    public function parse(string $section, array $row): RarAuthorizationData
    {
        $section = strtoupper($section);
        $branch = $this->map($row['branch'] ?? null);
        $organisation = $this->map($branch['organisationInfo'] ?? null);
        $officeAddress = $this->map($organisation['address'] ?? null);
        $siteAddress = $this->map($branch['address'] ?? null);

        $legalName = TextNormalizer::clean($organisation['name'] ?? null) ?? $this->nameFromBranch($branch['name'] ?? null);

        if ($legalName === null) {
            throw new RarParseException('The authorisation names no organisation.');
        }

        $office = $this->location($officeAddress, RomanianCounties::resolve($officeAddress['county'] ?? null));
        $siteCounty = RomanianCounties::resolve($siteAddress['county'] ?? null) ?? $office->countyCode;
        $site = $this->location($siteAddress, $siteCounty, $office);

        $phones = $this->uniquePhones([
            ...PhoneNormalizer::extractAll($branch['telephoneNo'] ?? null),
            ...PhoneNormalizer::extractAll(data_get($branch, 'contactPerson.person.telephoneNo')),
        ]);

        return new RarAuthorizationData(
            system: $section,
            status: TextNormalizer::clean($row['status'] ?? null),
            number: TextNormalizer::clean($row['no'] ?? null),
            exitNumber: TextNormalizer::clean($row['exitNo'] ?? null),
            auditFileNumber: TextNormalizer::clean($row['auditFileNo'] ?? null),
            stationCode: TextNormalizer::clean($row['stationCode'] ?? null),
            authorizationClass: $this->authorizationClass($section, $row),
            validFrom: $this->date($row['validFrom'] ?? null),
            validUntil: $this->date($row['validUntil'] ?? null),
            initiallyAuthorizedAt: $this->date($row['initialAuthDate'] ?? null),
            revisionDate: $this->date($row['revisionDate'] ?? null),
            revisionNumber: is_numeric($row['revisionNo'] ?? null) ? (int) $row['revisionNo'] : null,
            workstations: $this->positiveInt($row['noOfWorkstation'] ?? null),
            employees: $this->positiveInt($row['noOfEmployees'] ?? null),
            isMobile: filter_var($row['isMobileWorkshop'] ?? false, FILTER_VALIDATE_BOOLEAN),
            company: new CompanyData(
                legalName: $legalName,
                cui: Identifiers::cui($organisation['taxRegisterNo'] ?? null),
                registrationNumber: Identifiers::registrationNumber($organisation['tradeRegisterNo'] ?? null),
                isVatPayer: match (strtoupper(trim((string) ($organisation['fiscalIndicator'] ?? '')))) {
                    'RO' => true,
                    '-' => false,
                    default => null,
                },
                registeredOffice: $office,
                phones: PhoneNormalizer::extractAll($organisation['telephoneNo'] ?? null),
                emails: $this->emails($organisation['email'] ?? null),
            ),
            location: $site,
            phones: $phones,
            activities: $this->activities($section, $row),
            brands: $this->brands($row),
            summary: Arr::except($row, ['branch', ...self::ACTIVITY_KEYS]),
        );
    }

    private function location(array $address, ?string $countyCode, ?LocationData $office = null): LocationData
    {
        $original = TextNormalizer::clean($address['originalAddress'] ?? null);
        $street = TextNormalizer::clean($address['street'] ?? null);
        $number = TextNormalizer::clean($address['streetNo'] ?? null);
        $city = TextNormalizer::clean($address['city'] ?? null);
        $unit = TextNormalizer::clean($address['administrativeTerritorialUnit'] ?? null);

        // Bucharest is one locality; the registry puts the sector in the city field.
        $locality = $countyCode === 'B' ? 'București' : TextNormalizer::titleIfShouting($city ?? $unit);
        $municipality = $countyCode === 'B' ? TextNormalizer::titleIfShouting($city) : TextNormalizer::titleIfShouting($unit);

        $composed = $original ?? $this->join([
            $street,
            $number !== null ? 'nr. '.$number : null,
            TextNormalizer::clean($address['otherDetails'] ?? null),
            $city,
            $countyCode !== null ? 'jud. '.RomanianCounties::name($countyCode) : null,
        ]);

        [$lat, $lng, $quality, $confidence] = $this->coordinates($address['gpsLocation'] ?? null, $countyCode, $composed, $locality, $office);

        return new LocationData(
            address: $composed,
            street: $street,
            streetNumber: $number,
            locality: $locality,
            municipality: $municipality,
            countyCode: $countyCode,
            postalCode: $this->postalCode($address['postalCode'] ?? null),
            latitude: $lat,
            longitude: $lng,
            coordinateQuality: $quality,
            coordinateConfidence: $confidence,
            rawCoordinates: is_string($address['gpsLocation'] ?? null) ? $address['gpsLocation'] : null,
        );
    }

    /** @return array{0: float|null, 1: float|null, 2: string, 3: int} */
    private function coordinates(mixed $raw, ?string $countyCode, ?string $address, ?string $locality, ?LocationData $office): array
    {
        $point = Geo::parsePair($raw);

        if ($point === null) {
            return [null, null, 'missing', 0];
        }

        if ($countyCode === null || ! RomanianCounties::isPlausible($countyCode, $point['lat'], $point['lng'])) {
            return [null, null, 'implausible', 0];
        }

        $officePoint = $office === null ? null : Geo::parsePair($office->rawCoordinates);
        $samePointAsOffice = $officePoint !== null && $officePoint['lat'] === $point['lat'] && $officePoint['lng'] === $point['lng'];

        if ($samePointAsOffice && AddressNormalizer::similarity($address, $office->address, $countyCode) < 0.6) {
            // The office's point at a different address. A town-level point is still right when
            // both are in the same town; anything finer would place the workshop at the office.
            $sameTown = $point['precision'] <= 2 && TextNormalizer::fold($locality) !== '' && TextNormalizer::fold($locality) === TextNormalizer::fold($office->locality);

            if (! $sameTown) {
                return [null, null, 'registered_office', 0];
            }
        }

        return match (true) {
            $point['precision'] <= 2 => [$point['lat'], $point['lng'], 'approximate', 25],
            $point['precision'] === 3 => [$point['lat'], $point['lng'], 'precise', 45],
            default => [$point['lat'], $point['lng'], 'precise', 60],
        };
    }

    /** @return list<ActivityData> */
    private function activities(string $section, array $row): array
    {
        return match ($section) {
            'SERVICE' => $this->serviceActivities($row),
            'ITP' => $this->itpActivities($row),
            'GPL' => $this->flatActivities('GPL', $this->items($row['authorizedActivitiesGpl'] ?? null)),
            'TLV' => $this->flatActivities('TLV', $this->items($row['authorizationTlvActivitiesDetails'] ?? null) ?: $this->items($row['evaluationTlvActivitiesDetails'] ?? null)),
            'B4' => $this->b4Activities($row),
            default => [],
        };
    }

    /**
     * Each entry of authorizedActivities is a heading (A1_2) with the sub-activities RAR ticked
     * under it and the vehicle categories and limitations that apply to all of them. Every code is
     * kept as its own row, carrying what its heading said.
     *
     * @return list<ActivityData>
     */
    private function serviceActivities(array $row): array
    {
        $activities = [];
        $suspended = $this->activeSuspensions($row);

        foreach ($this->items($row['authorizedActivities'] ?? null) as $entry) {
            $entryCode = $this->code($entry['activity'] ?? null);

            if ($entryCode === null) {
                continue;
            }

            $categories = $this->strings([
                ...$this->items($entry['homologationCategories'] ?? null),
                ...$this->items($entry['class1HomologationCategories'] ?? null),
                ...$this->items($entry['class2HomologationCategories'] ?? null),
                ...$this->items($entry['class3HomologationCategories'] ?? null),
            ]);
            $limitations = $this->strings([
                ...$this->items($entry['limitations'] ?? null),
                ...$this->items($entry['class1Limitations'] ?? null),
                ...$this->items($entry['class2Limitations'] ?? null),
                ...$this->items($entry['class3Limitations'] ?? null),
            ]);
            $observations = $this->strings([$entry['remarks'] ?? null]);

            $codes = array_values(array_unique(array_filter([$entryCode, ...array_map($this->code(...), $this->items($entry['activities'] ?? null))])));

            foreach ($codes as $code) {
                $existing = $activities[$code] ?? null;

                $activities[$code] = new ActivityData(
                    code: $code,
                    entryCode: $existing->entryCode ?? $entryCode,
                    parentCode: $this->hierarchicalParent($code),
                    depth: substr_count($code, '_'),
                    rawDescription: $this->nomenclature->activity('SERVICE', $code),
                    vehicleCategories: $this->strings([...($existing->vehicleCategories ?? []), ...$categories]),
                    limitations: $this->strings([...($existing->limitations ?? []), ...$limitations]),
                    restrictions: $suspended[$code] ?? [],
                    observations: $this->strings([...($existing->observations ?? []), ...$observations]),
                    raw: $code === $entryCode ? $entry : ($existing->raw ?? ['listed_under' => $entryCode]),
                );
            }
        }

        return array_values($activities);
    }

    /** @return list<ActivityData> */
    private function itpActivities(array $row): array
    {
        $activities = ['ITP' => new ActivityData('ITP', 'ITP', null, 0, $this->nomenclature->activity('ITP', 'ITP'), raw: ['station_code' => $row['stationCode'] ?? null])];
        $details = $this->items($row['itpAuthClassDetails'] ?? null);

        if ($details === []) {
            $details = array_map(fn (mixed $class): array => ['itpAuthClass' => $class], $this->items($row['itpAuthClass'] ?? null));
        }

        foreach ($details as $detail) {
            $class = $this->code($detail['itpAuthClass'] ?? null);

            if ($class === null) {
                continue;
            }

            $limitations = array_map(
                fn (array $limitation): string => $this->nomenclature->itpLimitation((string) ($limitation['limitation'] ?? ''), $limitation['limitationValue'] ?? null),
                array_filter([...$this->items($detail['itpAuthLimitations'] ?? null), ...$this->items($detail['lineLimitations'] ?? null)], 'is_array'),
            );
            $restrictions = array_map(
                fn (mixed $code): array => ['code' => (string) $code, 'text' => $this->nomenclature->itpInterdiction((string) $code)],
                array_filter([...$this->items($detail['itpAuthInterdictions'] ?? null), ...$this->items($detail['lineInterdictions'] ?? null)], 'is_string'),
            );
            $observations = array_map(
                fn (mixed $code): string => $this->nomenclature->itpObservation((string) $code),
                array_filter([...$this->items($detail['itpAuthObservations'] ?? null), ...$this->items($detail['lineObservations'] ?? null)], 'is_string'),
            );

            $existing = $activities[$class] ?? null;
            $activities[$class] = new ActivityData(
                code: $class,
                entryCode: 'ITP',
                parentCode: 'ITP',
                depth: 1,
                rawDescription: $this->nomenclature->itpClass($class),
                limitations: $this->strings([...($existing->limitations ?? []), ...$limitations]),
                restrictions: $this->uniqueRestrictions([...($existing->restrictions ?? []), ...$restrictions]),
                observations: $this->strings([...($existing->observations ?? []), ...$observations]),
                raw: ['lines' => [...($existing->raw['lines'] ?? []), $detail]],
            );
        }

        return array_values($activities);
    }

    /** @return list<ActivityData> */
    private function flatActivities(string $section, array $entries): array
    {
        $activities = [];

        foreach ($entries as $entry) {
            $code = is_array($entry) ? $this->code($entry['activity'] ?? null) : null;

            if ($code === null) {
                continue;
            }

            $activities[$code] = new ActivityData(
                code: $code,
                entryCode: $code,
                parentCode: null,
                depth: 0,
                rawDescription: $this->nomenclature->activity($section, $code),
                limitations: $this->strings($this->items($entry['limitations'] ?? null)),
                raw: $entry,
            );
        }

        return array_values($activities);
    }

    /**
     * B4 authorises fitting specific homologated components (C9 bull bars, C40 winches, C56
     * suspension tuning…). Each component becomes its own row under the activity, so a search
     * for "C40" finds the workshop.
     *
     * @return list<ActivityData>
     */
    private function b4Activities(array $row): array
    {
        $activities = [];

        foreach ($this->items($row['authorizationB4ActivitiesDetails'] ?? null) as $entry) {
            $entryCode = is_array($entry) ? $this->code($entry['activity'] ?? null) : null;

            if ($entryCode === null) {
                continue;
            }

            $details = array_values(array_filter($this->items($entry['activities'] ?? null), 'is_array'));
            $components = [];

            foreach ($details as $detail) {
                $component = $this->code($detail['installationComponent'] ?? null);

                if ($component !== null) {
                    $components[$component][] = $detail;
                }
            }

            $activities[$entryCode] = new ActivityData(
                code: $entryCode,
                entryCode: $entryCode,
                parentCode: $this->hierarchicalParent($entryCode),
                depth: substr_count($entryCode, '_'),
                rawDescription: $this->nomenclature->activity('B4', $entryCode),
                vehicleCategories: $this->strings($this->detailCategories($details)),
                limitations: $this->strings([...$this->items($entry['limitations'] ?? null), ...array_column($details, 'limitation')]),
                observations: $this->strings(array_map(fn (string $component): string => $component.' – '.($this->nomenclature->b4Component($component) ?? 'componentă necunoscută'), array_keys($components))),
                raw: $entry,
            );

            foreach ($components as $component => $componentDetails) {
                $code = $entryCode.'_'.$component;

                $activities[$code] = new ActivityData(
                    code: $code,
                    entryCode: $entryCode,
                    parentCode: $entryCode,
                    depth: substr_count($entryCode, '_') + 1,
                    rawDescription: $this->nomenclature->b4Component($component),
                    vehicleCategories: $this->strings($this->detailCategories($componentDetails)),
                    limitations: $this->strings(array_column($componentDetails, 'limitation')),
                    observations: $this->strings(array_column($componentDetails, 'installationType')),
                    raw: ['component' => $component, 'details' => $componentDetails],
                    displayCode: str_replace('_', '.', $entryCode).' · '.$component,
                );
            }
        }

        return array_values($activities);
    }

    /** @return list<mixed> */
    private function detailCategories(array $details): array
    {
        $categories = [];

        foreach ($details as $detail) {
            array_push(
                $categories,
                ...$this->items($detail['installationVehicleCategory'] ?? null),
                ...$this->items($detail['initialVehicleCategories'] ?? null),
                ...$this->items($detail['finalVehicleCategories'] ?? null),
            );
        }

        return $categories;
    }

    /**
     * Suspensions in force today, attached to the activities they cover. Past and future ones
     * stay in the summary; only a current suspension limits what the workshop may do now.
     *
     * @return array<string, list<array{code: string, text: string}>>
     */
    private function activeSuspensions(array $row): array
    {
        $today = CarbonImmutable::now('Europe/Bucharest')->startOfDay();
        $restrictions = [];

        foreach ($this->items($row['authorizationSuspensionInfo'] ?? null) as $suspension) {
            if (! is_array($suspension)) {
                continue;
            }

            $start = $this->date($suspension['suspensionStartDate'] ?? null);
            $end = $this->date($suspension['suspensionEndDate'] ?? null);

            if ($start === null || $start->greaterThan($today) || ($end !== null && $end->lessThan($today))) {
                continue;
            }

            $text = 'Suspendat '.$start->format('d.m.Y').($end !== null ? ' – '.$end->format('d.m.Y') : '').' ('.($suspension['no'] ?? 'fără număr').')';

            foreach ($this->items($suspension['suspendedActivities'] ?? null) as $code) {
                if (is_string($code) && $code !== '') {
                    $restrictions[$code][] = ['code' => 'SUSPENSION', 'text' => $text];
                }
            }
        }

        return $restrictions;
    }

    /**
     * SERVICE classes are given per activity group (A1 in class II, A2 in class III…); ITP and B4
     * carry one class for the whole authorisation. Kept as RAR's own codes, comma separated.
     */
    private function authorizationClass(string $section, array $row): ?string
    {
        $classes = match ($section) {
            'SERVICE' => array_merge(...array_map(fn (mixed $type): array => is_array($type) ? $this->items($type['serviceType'] ?? null) : [], $this->items($row['serviceTypes'] ?? null)) ?: [[]]),
            'ITP' => $this->items($row['itpAuthClass'] ?? null),
            'B4' => [$row['b4Class'] ?? null],
            default => [],
        };

        $classes = $this->strings($classes);
        sort($classes);

        return $classes === [] ? null : implode(',', $classes);
    }

    /** @return list<string> vehicle makes the workshop is an authorised service for */
    private function brands(array $row): array
    {
        return $this->strings(array_map(
            fn (mixed $authorization): mixed => is_array($authorization) ? ($authorization['vehicleBrand'] ?? null) : null,
            $this->items($row['serviceAuthorizations'] ?? null),
        ));
    }

    private function hierarchicalParent(string $code): ?string
    {
        if (preg_match(self::HIERARCHICAL, $code) !== 1 && preg_match('/^B4(?:_\d+)+$/', $code) !== 1) {
            return null;
        }

        $position = strrpos($code, '_');

        return $position === false ? null : substr($code, 0, $position);
    }

    private function date(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            // Stored as UTC instants of a local midnight ("2009-06-14T21:00:00.000+0000" is
            // 15 June), so the date is read in Bucharest time.
            return CarbonImmutable::parse($value)->setTimezone('Europe/Bucharest')->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }

    /** @return list<string> */
    private function emails(mixed $value): array
    {
        $value = TextNormalizer::clean($value);

        if ($value === null || preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $value, $matches) === 0) {
            return [];
        }

        $emails = array_filter(
            array_map(fn (string $email): string => mb_strtolower($email), $matches[0]),
            fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false,
        );

        return array_values(array_unique($emails));
    }

    /**
     * @param  list<PhoneNumber>  $phones
     * @return list<PhoneNumber>
     */
    private function uniquePhones(array $phones): array
    {
        $unique = [];

        foreach ($phones as $phone) {
            $unique[$phone->e164] ??= $phone;
        }

        return array_values($unique);
    }

    private function nameFromBranch(mixed $value): ?string
    {
        $value = TextNormalizer::clean($value);

        return $value === null ? null : TextNormalizer::clean(explode(',', $value, 2)[0]);
    }

    private function postalCode(mixed $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) (is_scalar($value) ? $value : '')) ?? '';

        return strlen($digits) === 6 ? $digits : null;
    }

    private function positiveInt(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    private function code(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = strtoupper(trim($value));

        return preg_match('/^[A-Z0-9_]{1,40}$/', $value) === 1 ? $value : null;
    }

    /** @param list<mixed> $values @return list<string> */
    private function strings(array $values): array
    {
        $clean = [];

        foreach ($values as $value) {
            $value = is_scalar($value) ? TextNormalizer::clean((string) $value) : null;

            if ($value !== null) {
                $clean[$value] = true;
            }
        }

        return array_keys($clean);
    }

    /**
     * @param  list<array{code: string|null, text: string}>  $restrictions
     * @return list<array{code: string|null, text: string}>
     */
    private function uniqueRestrictions(array $restrictions): array
    {
        $unique = [];

        foreach ($restrictions as $restriction) {
            $unique[($restriction['code'] ?? '').'|'.$restriction['text']] = $restriction;
        }

        return array_values($unique);
    }

    private function join(array $parts): ?string
    {
        $parts = array_filter($parts, fn (mixed $part): bool => is_string($part) && $part !== '');

        return $parts === [] ? null : implode(', ', $parts);
    }

    private function map(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    /** @return list<mixed> */
    private function items(mixed $value): array
    {
        if (! is_array($value)) {
            return $value === null || $value === '' ? [] : [$value];
        }

        return array_values($value);
    }
}
