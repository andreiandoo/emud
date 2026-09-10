<?php

namespace App\Workshops\Sources\Onrc;

use App\Workshops\Data\LocationData;
use App\Workshops\Support\Identifiers;
use App\Workshops\Support\RomanianCounties;
use App\Workshops\Support\TextNormalizer;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Reads the payload the ONRC importer stores: one OD_FIRME row, with the company's authorised
 * CAEN codes and status history attached under CAEN and STARI.
 */
class OnrcCompanyParser
{
    public function parse(array $payload): OnrcCompany
    {
        $registration = Identifiers::registrationNumber($payload['COD_INMATRICULARE'] ?? null);
        $name = TextNormalizer::clean($payload['DENUMIRE'] ?? null);

        if ($registration === null || $name === null) {
            throw new \InvalidArgumentException('ONRC row without registration number or name.');
        }

        $county = RomanianCounties::resolve($payload['ADR_JUDET'] ?? null) ?? RomanianCounties::resolve($payload['ADR_LOCALITATE'] ?? null);
        $locality = $this->locality($payload['ADR_LOCALITATE'] ?? null, $county);

        $street = $this->join([
            TextNormalizer::clean($payload['ADR_DEN_STRADA'] ?? null),
            ($number = TextNormalizer::clean($payload['ADR_NR_STRADA'] ?? null)) !== null ? 'nr. '.$number : null,
            ($block = TextNormalizer::clean($payload['ADR_BLOC'] ?? null)) !== null ? 'bl. '.$block : null,
            ($entrance = TextNormalizer::clean($payload['ADR_SCARA'] ?? null)) !== null ? 'sc. '.$entrance : null,
            ($floor = TextNormalizer::clean($payload['ADR_ETAJ'] ?? null)) !== null ? 'et. '.$floor : null,
            ($flat = TextNormalizer::clean($payload['ADR_APARTAMENT'] ?? null)) !== null ? 'ap. '.$flat : null,
            TextNormalizer::clean($payload['ADR_COMPLETARE'] ?? null),
        ]);

        $sector = TextNormalizer::clean($payload['ADR_SECTOR'] ?? null);
        $address = $this->join([
            $street,
            $county === 'B' && $sector !== null && ctype_digit($sector) ? 'Sector '.$sector : null,
            $locality,
            $county !== null && $county !== 'B' ? 'jud. '.RomanianCounties::name($county) : null,
        ]);

        $postal = preg_replace('/\D+/', '', (string) ($payload['ADR_COD_POSTAL'] ?? '')) ?? '';

        return new OnrcCompany(
            registrationNumber: $registration,
            legalName: $name,
            cui: Identifiers::cui($payload['CUI'] ?? null),
            euid: TextNormalizer::clean($payload['EUID'] ?? null),
            legalForm: TextNormalizer::clean($payload['FORMA_JURIDICA'] ?? null),
            registeredOn: $this->date($payload['DATA_INMATRICULARE'] ?? null),
            office: new LocationData(
                address: $address,
                street: TextNormalizer::clean($payload['ADR_DEN_STRADA'] ?? null),
                streetNumber: $number,
                locality: $locality,
                countyCode: $county,
                postalCode: strlen($postal) === 6 ? $postal : null,
            ),
            website: $this->website($payload['WEB'] ?? null),
            caen: array_values(array_filter(array_map(fn (mixed $entry): ?array => is_array($entry) && isset($entry['COD']) ? [
                'code' => (string) $entry['COD'],
                'version' => (string) ($entry['VER'] ?? ''),
                'label' => TextNormalizer::clean($entry['DENUMIRE'] ?? null),
            ] : null, (array) ($payload['CAEN'] ?? [])))),
            statuses: array_values(array_filter(array_map(fn (mixed $entry): ?array => is_array($entry) && isset($entry['COD']) ? [
                'code' => (string) $entry['COD'],
                'label' => TextNormalizer::clean($entry['DENUMIRE'] ?? null),
            ] : null, (array) ($payload['STARI'] ?? [])))),
            automotive: (bool) ($payload['AUTO'] ?? false),
        );
    }

    /** "Bucureşti Sectorul 6" is Bucharest; "Mun. Cluj-Napoca" is Cluj-Napoca. */
    private function locality(mixed $value, ?string $county): ?string
    {
        $value = TextNormalizer::clean($value);

        if ($value === null) {
            return null;
        }

        if ($county === 'B' && preg_match('/^bucure/i', TextNormalizer::fold($value)) === 1) {
            return 'București';
        }

        $value = preg_replace('/^(?:Mun\.|Municipiul|Oraș|Oras|Or\.|Com\.|Comuna|Sat)\s+/iu', '', $value) ?? $value;

        return TextNormalizer::titleIfShouting($value);
    }

    private function website(mixed $value): ?string
    {
        $value = TextNormalizer::clean($value);

        if ($value === null || ! str_contains($value, '.')) {
            return null;
        }

        return preg_match('#^https?://#i', $value) === 1 ? $value : 'http://'.$value;
    }

    private function date(mixed $value): ?string
    {
        $value = TextNormalizer::clean($value);

        if ($value === null) {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('d/m/Y', $value)?->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    private function join(array $parts): ?string
    {
        $parts = array_filter($parts, fn (mixed $part): bool => is_string($part) && $part !== '');

        return $parts === [] ? null : implode(', ', $parts);
    }
}
