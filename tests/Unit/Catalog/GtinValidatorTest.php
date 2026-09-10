<?php

namespace Tests\Unit\Catalog;

use App\Catalog\Normalization\GtinValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class GtinValidatorTest extends TestCase
{
    public function test_a_real_ean_13_is_accepted_and_padded_to_fourteen_digits(): void
    {
        $this->assertSame('04006381333931', (new GtinValidator)->canonical('4006381333931'));
    }

    public function test_upc_a_and_its_ean_13_form_are_the_same_identity(): void
    {
        $validator = new GtinValidator;

        $this->assertSame($validator->canonical('036000291452'), $validator->canonical('0036000291452'));
    }

    public function test_formatting_characters_are_ignored(): void
    {
        $this->assertSame('04006381333931', (new GtinValidator)->canonical('400 6381-333931'));
    }

    /** @return array<string, array{0: string}> */
    public static function rejectedBarcodes(): array
    {
        return [
            'all zeros placeholder' => ['0000000000000'],
            'repeated digit placeholder' => ['1111111111111'],
            'wrong check digit' => ['4006381333932'],
            'unsupported length' => ['40063813339'],
            'a sku pasted into the column' => ['BOS-0986494123'],
            'empty' => [''],
        ];
    }

    #[DataProvider('rejectedBarcodes')]
    public function test_placeholders_and_typos_are_rejected(string $barcode): void
    {
        $this->assertNull((new GtinValidator)->canonical($barcode));
    }

    public function test_lookup_forms_only_drop_leading_zeros(): void
    {
        $validator = new GtinValidator;

        $this->assertSame(
            ['00000096385074', '0000096385074', '000096385074', '96385074'],
            $validator->lookupForms($validator->canonical('96385074')),
        );

        // A GTIN-14 with a real leading digit has no shorter equivalent.
        $this->assertSame(['14006381333938'], $validator->lookupForms('14006381333938'));
    }
}
