<?php

namespace Tests\Unit\Catalog;

use App\Catalog\Normalization\IdentifierNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class IdentifierNormalizerTest extends TestCase
{
    #[DataProvider('numbers')]
    public function test_it_normalizes_and_compacts_part_numbers(string $input, string $normalized, string $compact): void
    {
        $service = new IdentifierNormalizer();

        self::assertSame($normalized, $service->normalize($input));
        self::assertSame($compact, $service->compact($input));
    }

    public static function numbers(): array
    {
        return [
            [' hu 711/51 x ', 'HU 711/51 X', 'HU71151X'],
            ['0 986 479 123', '0 986 479 123', '0986479123'],
            ['LR-073.669', 'LR-073.669', 'LR073669'],
        ];
    }
}
