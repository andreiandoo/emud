<?php

namespace Tests\Unit\Workshops;

use App\Workshops\Support\AddressNormalizer;
use App\Workshops\Support\CompanyNameNormalizer;
use App\Workshops\Support\Identifiers;
use App\Workshops\Support\TokenSimilarity;
use PHPUnit\Framework\TestCase;

class CompanyAndAddressNormalizerTest extends TestCase
{
    public function test_legal_forms_and_punctuation_do_not_separate_one_company(): void
    {
        $this->assertSame('ab autobella service', CompanyNameNormalizer::normalize('S.C. AB AUTOBELLA SERVICE S.R.L.'));
        $this->assertSame('ab autobella service', CompanyNameNormalizer::normalize('AB AUTOBELLA SERVICE SRL'));
        $this->assertSame('hope sped', CompanyNameNormalizer::normalize('"HOPE SPED" SRL'));
        $this->assertSame('ionescu i p costel', CompanyNameNormalizer::normalize('IONESCU I.P. COSTEL PERSOANA FIZICA AUTORIZATA'));
        $this->assertSame('auto tehnic serban', CompanyNameNormalizer::normalize('AUTO TEHNIC ŞERBAN S.R.L.'));
    }

    public function test_the_legal_form_is_read_but_never_removed_from_the_legal_name(): void
    {
        $this->assertSame('SRL-D', CompanyNameNormalizer::legalForm('X S.R.L.-D.'));
        $this->assertSame('SRL', CompanyNameNormalizer::legalForm('X SRL'));
        $this->assertSame('SA', CompanyNameNormalizer::legalForm('AITRANS S.A.'));
        $this->assertSame('PFA', CompanyNameNormalizer::legalForm('POPESCU ION PFA'));
        $this->assertSame('II', CompanyNameNormalizer::legalForm('POPESCU ION I.I.'));
        $this->assertSame('IF', CompanyNameNormalizer::legalForm('POPESCU I.F.'));
        $this->assertNull(CompanyNameNormalizer::legalForm('CASA'));
        $this->assertSame('casa', CompanyNameNormalizer::normalize('CASA SRL'));
    }

    public function test_name_similarity_ignores_word_order_and_one_letter_typos(): void
    {
        $this->assertSame(1.0, CompanyNameNormalizer::similarity('SERVICE AUTO ION SRL', 'ION SERVICE AUTO S.R.L.'));
        $this->assertSame(1.0, CompanyNameNormalizer::similarity('AUTOBELLA', 'AUTOBELA'));
        $this->assertLessThan(0.5, CompanyNameNormalizer::similarity('VULCANIZARE NORD', 'AUTO TEHNIC ȘERBAN'));
        $this->assertFalse(TokenSimilarity::sameToken('90', '9'));
    }

    public function test_abbreviations_are_expanded_to_one_spelling(): void
    {
        $this->assertSame('soseaua cristianului nr 6', AddressNormalizer::normalize('Șos. Cristianului nr. 6'));
        $this->assertSame('bulevardul eroilor 5', AddressNormalizer::normalize('B-dul Eroilor 5'));
    }

    public function test_one_address_typed_two_ways_is_one_address(): void
    {
        $this->assertGreaterThanOrEqual(0.9, AddressNormalizer::similarity('Botoşani, Str. Pacea nr. 90, jud. BOTOŞANI', 'STRADA PACEA NR. 90, BOTOSANI, JUD. BOTOSANI', 'BT'));
        $this->assertGreaterThanOrEqual(0.8, AddressNormalizer::similarity('SAT GÎRCINA, COMUNA GÎRCINA, DN 15D, JUDEȚ NEAMŢ', 'Comuna Gârcina, DN 15D, Jud. Neamț', 'NT'));
        $this->assertGreaterThanOrEqual(0.8, AddressNormalizer::similarity('ȘOS. CRISTIANULUI NR. 6 (HALA REPARAȚII), BRAȘOV', 'Șos. Cristianului nr. 6, Brașov', 'BV'));
    }

    public function test_different_house_numbers_are_different_buildings(): void
    {
        $this->assertSame(0.0, AddressNormalizer::similarity('Str. Pacea nr. 90', 'Str. Pacea nr. 9', 'BT'));
        $this->assertSame(0.0, AddressNormalizer::similarity('', 'Str. Pacea nr. 9', 'BT'));
    }

    public function test_fiscal_codes_are_compared_as_digits_and_checked(): void
    {
        $this->assertSame('15428073', Identifiers::cui('RO 15428073'));
        $this->assertSame('15428073', Identifiers::cui(' ro15428073 '));
        $this->assertNull(Identifiers::cui('0'));
        $this->assertNull(Identifiers::cui('-'));
        $this->assertTrue(Identifiers::isValidCui('15428073'));
        $this->assertTrue(Identifiers::isValidCui('RO20963285'));
        $this->assertFalse(Identifiers::isValidCui('15428074'));
        $this->assertSame('J08/346/2007', Identifiers::registrationNumber(' j08/346/2007 '));
    }
}
