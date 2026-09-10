<?php

namespace App\Workshops\Support;

/**
 * The 41 counties and Bucharest, keyed by the ISO 3166-2:RO code (which is also the licence plate
 * prefix). Bucharest is "B".
 *
 * A reference, not the source of truth: the RAR importer discovers the county list from the
 * registry itself and only maps it onto these codes. The seat coordinates exist for one sanity
 * check, catching a workshop in Brașov that a source placed in Bucharest.
 */
class RomanianCounties
{
    /** @var array<string, array{0: string, 1: string, 2: float, 3: float}> code => [name, seat, lat, lng] */
    public const ALL = [
        'AB' => ['Alba', 'Alba Iulia', 46.0677, 23.5700],
        'AR' => ['Arad', 'Arad', 46.1866, 21.3123],
        'AG' => ['Argeș', 'Pitești', 44.8565, 24.8692],
        'BC' => ['Bacău', 'Bacău', 46.5670, 26.9146],
        'BH' => ['Bihor', 'Oradea', 47.0465, 21.9189],
        'BN' => ['Bistrița-Năsăud', 'Bistrița', 47.1357, 24.4930],
        'BT' => ['Botoșani', 'Botoșani', 47.7486, 26.6694],
        'BV' => ['Brașov', 'Brașov', 45.6427, 25.5887],
        'BR' => ['Brăila', 'Brăila', 45.2692, 27.9575],
        'B' => ['București', 'București', 44.4268, 26.1025],
        'BZ' => ['Buzău', 'Buzău', 45.1500, 26.8333],
        'CS' => ['Caraș-Severin', 'Reșița', 45.3008, 21.8892],
        'CL' => ['Călărași', 'Călărași', 44.2000, 27.3333],
        'CJ' => ['Cluj', 'Cluj-Napoca', 46.7712, 23.6236],
        'CT' => ['Constanța', 'Constanța', 44.1598, 28.6348],
        'CV' => ['Covasna', 'Sfântu Gheorghe', 45.8667, 25.7833],
        'DB' => ['Dâmbovița', 'Târgoviște', 44.9254, 25.4567],
        'DJ' => ['Dolj', 'Craiova', 44.3302, 23.7949],
        'GL' => ['Galați', 'Galați', 45.4353, 28.0080],
        'GR' => ['Giurgiu', 'Giurgiu', 43.9037, 25.9699],
        'GJ' => ['Gorj', 'Târgu Jiu', 45.0350, 23.2744],
        'HR' => ['Harghita', 'Miercurea Ciuc', 46.3583, 25.8047],
        'HD' => ['Hunedoara', 'Deva', 45.8833, 22.9000],
        'IL' => ['Ialomița', 'Slobozia', 44.5667, 27.3667],
        'IS' => ['Iași', 'Iași', 47.1585, 27.6014],
        'IF' => ['Ilfov', 'Buftea', 44.5700, 25.9500],
        'MM' => ['Maramureș', 'Baia Mare', 47.6567, 23.5850],
        'MH' => ['Mehedinți', 'Drobeta-Turnu Severin', 44.6369, 22.6597],
        'MS' => ['Mureș', 'Târgu Mureș', 46.5425, 24.5575],
        'NT' => ['Neamț', 'Piatra Neamț', 46.9275, 26.3708],
        'OT' => ['Olt', 'Slatina', 44.4300, 24.3717],
        'PH' => ['Prahova', 'Ploiești', 44.9364, 26.0133],
        'SM' => ['Satu Mare', 'Satu Mare', 47.7900, 22.8900],
        'SJ' => ['Sălaj', 'Zalău', 47.1911, 23.0572],
        'SB' => ['Sibiu', 'Sibiu', 45.7928, 24.1522],
        'SV' => ['Suceava', 'Suceava', 47.6514, 26.2556],
        'TR' => ['Teleorman', 'Alexandria', 43.9686, 25.3325],
        'TM' => ['Timiș', 'Timișoara', 45.7489, 21.2087],
        'TL' => ['Tulcea', 'Tulcea', 45.1667, 28.8000],
        'VS' => ['Vaslui', 'Vaslui', 46.6333, 27.7333],
        'VL' => ['Vâlcea', 'Râmnicu Vâlcea', 45.1000, 24.3667],
        'VN' => ['Vrancea', 'Focșani', 45.6967, 27.1864],
    ];

    /** @var array<string, string>|null folded name => code */
    private static ?array $byFoldedName = null;

    /**
     * A county code from whatever a source wrote: "BV", "Brasov", "Județul Brașov",
     * "Bucureşti Sectorul 6", "Sector 3", "Municipiul București".
     */
    public static function resolve(mixed $value): ?string
    {
        $raw = TextNormalizer::clean($value);

        if ($raw === null) {
            return null;
        }

        $upper = strtoupper($raw);

        if (isset(self::ALL[$upper])) {
            return $upper;
        }

        $folded = TextNormalizer::fold($raw);
        $folded = preg_replace('/^(?:judetul|judet|jud|municipiul|mun)\s+/', '', $folded) ?? $folded;

        if (preg_match('/^(?:bucuresti\b.*|sector(?:ul)?\s*[1-6])$/', $folded) === 1) {
            return 'B';
        }

        return self::byFoldedName()[$folded] ?? null;
    }

    public static function name(string $code): ?string
    {
        return self::ALL[$code][0] ?? null;
    }

    /** @return array{lat: float, lng: float}|null */
    public static function seat(string $code): ?array
    {
        return isset(self::ALL[$code]) ? ['lat' => self::ALL[$code][2], 'lng' => self::ALL[$code][3]] : null;
    }

    /** The county whose seat is closest to a point. */
    public static function nearestSeat(float $lat, float $lng): string
    {
        $best = 'B';
        $bestDistance = INF;

        foreach (self::ALL as $code => [, , $seatLat, $seatLng]) {
            $distance = Geo::distanceMeters($lat, $lng, $seatLat, $seatLng);

            if ($distance < $bestDistance) {
                $best = $code;
                $bestDistance = $distance;
            }
        }

        return $best;
    }

    /**
     * Whether a point can plausibly be in a county. A county can stretch 150 km from its seat,
     * so distance alone proves little; what gives a misplaced point away is sitting next to
     * another county's seat while far from its own.
     */
    public static function isPlausible(string $code, float $lat, float $lng): bool
    {
        $seat = self::seat($code);

        if ($seat === null || ! Geo::inRomania($lat, $lng)) {
            return false;
        }

        $fromOwnSeat = Geo::distanceMeters($lat, $lng, $seat['lat'], $seat['lng']);

        if ($fromOwnSeat > 170_000) {
            return false;
        }

        $nearest = self::nearestSeat($lat, $lng);

        // Ilfov surrounds Bucharest; a point in one is routinely nearer the other's seat.
        $neighbours = ['B' => 'IF', 'IF' => 'B'];

        return $nearest === $code || ($neighbours[$code] ?? null) === $nearest || $fromOwnSeat <= 60_000;
    }

    /** @return array<string, string> */
    private static function byFoldedName(): array
    {
        if (self::$byFoldedName === null) {
            self::$byFoldedName = [];

            foreach (self::ALL as $code => [$name]) {
                self::$byFoldedName[TextNormalizer::fold($name)] = $code;
            }
        }

        return self::$byFoldedName;
    }
}
