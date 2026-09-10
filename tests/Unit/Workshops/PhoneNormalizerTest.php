<?php

namespace Tests\Unit\Workshops;

use App\Workshops\Support\PhoneNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PhoneNormalizerTest extends TestCase
{
    public static function mobileSpellings(): array
    {
        return [
            'spaced' => ['0722 123 456'],
            'compact' => ['0722123456'],
            'international' => ['+40 722 123 456'],
            'double zero' => ['0040722123456'],
            'country code without plus' => ['40722123456'],
            'trunk zero kept after country code' => ['+40 0722 123 456'],
            'dots and dashes' => ['0722.123-456'],
            'missing leading zero' => ['722123456'],
        ];
    }

    #[DataProvider('mobileSpellings')]
    public function test_every_way_of_writing_a_mobile_number_gives_one_canonical_form(string $raw): void
    {
        $phone = PhoneNormalizer::normalize($raw);

        $this->assertNotNull($phone);
        $this->assertSame('+40722123456', $phone->e164);
        $this->assertSame('mobile', $phone->type);
        $this->assertSame('0722123456', $phone->national());
        $this->assertSame('0722 123 456', $phone->display());
        $this->assertSame($raw, $phone->raw);
    }

    public function test_landlines_are_recognised_and_grouped_by_their_area_code(): void
    {
        $this->assertSame('landline', PhoneNormalizer::normalize('021 242 2744')->type);
        $this->assertSame('021 242 2744', PhoneNormalizer::normalize('0212422744')->display());
        $this->assertSame('0268 000 102', PhoneNormalizer::normalize('0268000102')->display());
        $this->assertSame('+40268000102', PhoneNormalizer::normalize('0268 000 102 int. 12')->e164);
    }

    public function test_a_foreign_number_is_kept_as_foreign(): void
    {
        $phone = PhoneNormalizer::normalize('+49 30 1234567');

        $this->assertSame('+49301234567', $phone->e164);
        $this->assertSame('international', $phone->type);
        $this->assertFalse($phone->isRomanian());
    }

    public function test_one_field_holding_several_numbers_yields_each_of_them(): void
    {
        $this->assertSame(['+40740041990', '+40743801585'], array_map(fn ($p) => $p->e164, PhoneNormalizer::extractAll('0740 041 990 /  0743 801 585')));
        $this->assertSame(['+40212422744', '+40722214506'], array_map(fn ($p) => $p->e164, PhoneNormalizer::extractAll('0212422744, 0722214506')));
        $this->assertSame(['+40722123456', '+40733123456'], array_map(fn ($p) => $p->e164, PhoneNormalizer::extractAll('0722123456 0733123456')));
        $this->assertCount(1, PhoneNormalizer::extractAll('0722123456; 0722 123 456'));
    }

    public function test_what_is_not_a_number_is_not_guessed(): void
    {
        $this->assertNull(PhoneNormalizer::normalize('12345'));
        $this->assertNull(PhoneNormalizer::normalize('nu are telefon'));
        $this->assertNull(PhoneNormalizer::normalize('0622123456'));
        $this->assertSame([], PhoneNormalizer::extractAll(null));
    }
}
