<?php

namespace Tests\Unit\Workshops;

use App\Workshops\Support\TextNormalizer;
use PHPUnit\Framework\TestCase;

class TextNormalizerTest extends TestCase
{
    public function test_cedilla_letters_become_the_romanian_comma_letters(): void
    {
        $this->assertSame('Brașov, Țesătorilor', TextNormalizer::clean('Braşov, Ţesătorilor'));
    }

    public function test_entities_and_runs_of_whitespace_are_repaired(): void
    {
        $this->assertSame('STR. ÎNVĂȚĂMÂNTULUI nr. 5', TextNormalizer::clean("  STR.&nbsp;&#206;NVĂŢĂMÂNTULUI \n nr. 5 ,"));
    }

    public function test_windows_1250_bytes_are_read_as_romanian(): void
    {
        // "Braşov" as a Windows-1250 system writes it: ş is the single byte 0xBA.
        $this->assertSame('Brașov', TextNormalizer::clean("Bra\xBAov"));
    }

    public function test_utf8_read_back_through_windows_1252_is_repaired(): void
    {
        $garbled = mb_convert_encoding('Brașov, Tâmpa', 'UTF-8', 'Windows-1252');

        $this->assertNotSame('Brașov, Tâmpa', $garbled);
        $this->assertSame('Brașov, Tâmpa', TextNormalizer::clean($garbled));
    }

    public function test_a_genuine_foreign_letter_is_left_alone(): void
    {
        $this->assertSame('Ägypten Motors', TextNormalizer::clean('Ägypten Motors'));
    }

    public function test_fold_makes_differently_typed_text_comparable(): void
    {
        $this->assertSame('sos cristianului nr 6', TextNormalizer::fold('Șos. Cristianului nr. 6'));
        $this->assertSame(TextNormalizer::fold('Șos. Cristianului nr. 6'), TextNormalizer::fold('SOS CRISTIANULUI, NR 6'));
    }

    public function test_only_text_in_capitals_is_title_cased(): void
    {
        $this->assertSame('Sat Lacu Sărat', TextNormalizer::titleIfShouting('SAT LACU SĂRAT'));
        $this->assertSame('McLaren', TextNormalizer::titleIfShouting('McLaren'));
    }

    public function test_empty_values_are_null(): void
    {
        $this->assertNull(TextNormalizer::clean('  , '));
        $this->assertNull(TextNormalizer::clean(null));
        $this->assertNull(TextNormalizer::clean(['not text']));
    }
}
