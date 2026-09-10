<?php

namespace Tests\Unit\Workshops;

use App\Workshops\Support\Geo;
use App\Workshops\Support\RomanianCounties;
use PHPUnit\Framework\TestCase;

class GeographyTest extends TestCase
{
    public function test_counties_are_found_however_a_source_writes_them(): void
    {
        $this->assertSame('BV', RomanianCounties::resolve('BV'));
        $this->assertSame('BV', RomanianCounties::resolve('Brasov'));
        $this->assertSame('BV', RomanianCounties::resolve('Județul Brașov'));
        $this->assertSame('B', RomanianCounties::resolve('Bucuresti'));
        $this->assertSame('B', RomanianCounties::resolve('Bucureşti Sectorul 6'));
        $this->assertSame('B', RomanianCounties::resolve('Sector 3'));
        $this->assertSame('B', RomanianCounties::resolve('Municipiul București'));
        $this->assertSame('CS', RomanianCounties::resolve('Caras-Severin'));
        $this->assertSame('BN', RomanianCounties::resolve('Bistrița-Năsăud'));
        $this->assertSame('SM', RomanianCounties::resolve('Satu Mare'));
        $this->assertNull(RomanianCounties::resolve('Atlantis'));
        $this->assertCount(42, RomanianCounties::ALL);
    }

    public function test_a_point_is_plausible_only_near_its_own_county(): void
    {
        $this->assertTrue(RomanianCounties::isPlausible('BV', 45.6624831, 25.5712348));
        // A Brașov workshop the registry placed in Bucharest, as seen in the real data.
        $this->assertFalse(RomanianCounties::isPlausible('BV', 44.4684269, 26.0495798));
        $this->assertTrue(RomanianCounties::isPlausible('IF', 44.45, 26.13));
        $this->assertTrue(RomanianCounties::isPlausible('B', 44.43, 26.10));
        $this->assertFalse(RomanianCounties::isPlausible('CT', 47.0, 22.0));
        $this->assertFalse(RomanianCounties::isPlausible('BV', 48.9, 25.5));
    }

    public function test_coordinates_keep_the_precision_they_were_given_with(): void
    {
        $this->assertSame(['lat' => 45.65, 'lng' => 25.6, 'precision' => 2], Geo::parsePair('45.65,25.60'));
        $this->assertSame(7, Geo::parsePair('44.4684269,26.0495798')['precision']);
        $this->assertNull(Geo::parsePair('0,0'));
        $this->assertNull(Geo::parsePair('not a point'));
        $this->assertNull(Geo::parsePair(null));
    }

    public function test_distances_are_great_circle_metres(): void
    {
        $bucharestToBrasov = Geo::distanceMeters(44.4268, 26.1025, 45.6427, 25.5887);

        $this->assertGreaterThan(130_000, $bucharestToBrasov);
        $this->assertLessThan(150_000, $bucharestToBrasov);

        $box = Geo::boundingBox(45.0, 25.0, 10_000);
        $this->assertEqualsWithDelta(0.09, $box['max_lat'] - 45.0, 0.001);
    }
}
