<?php

namespace Tests\Unit\Catalog;

use App\Catalog\Vehicles\Vin\VpicResultFlattener;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VpicResultFlattenerTest extends TestCase
{
    #[Test]
    public function it_flattens_vpic_variable_value_rows_case_insensitively(): void
    {
        $rows = [
            (object) ['Variable' => 'Make', 'Value' => 'LAND ROVER'],
            (object) ['variable' => 'Model', 'value' => 'Discovery Sport'],
            ['VariableName' => 'ModelYear', 'Value' => '2016'],
        ];

        $decoded = (new VpicResultFlattener)->flatten($rows);

        $this->assertSame('LAND ROVER', $decoded['Make']);
        $this->assertSame('Discovery Sport', $decoded['Model']);
        $this->assertSame('2016', $decoded['ModelYear']);
    }

    #[Test]
    public function it_preserves_distinct_repeated_values(): void
    {
        $rows = [
            ['Variable' => 'FuelTypePrimary', 'Value' => 'Diesel'],
            ['Variable' => 'FuelTypePrimary', 'Value' => 'Electric'],
            ['Variable' => 'FuelTypePrimary', 'Value' => 'Diesel'],
        ];

        $decoded = (new VpicResultFlattener)->flatten($rows);

        $this->assertSame(['Diesel', 'Electric'], $decoded['FuelTypePrimary']);
    }
}
