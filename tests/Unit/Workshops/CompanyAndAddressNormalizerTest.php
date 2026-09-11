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

    public function test_roads_house_letters_postal_codes_and_building_words_are_written_one_way(): void
    {
        $this->assertSame('sat geamana comuna bradu dn65 nr 2', AddressNormalizer::normalize('SAT GEAMANA, COMUNA BRADU, DN 65, NR.2'));
        $this->assertSame(1.0, AddressNormalizer::similarity('SAT GEAMANA, COMUNA BRADU, DN65, NR 2, JUDEȚUL ARGEȘ', 'SAT GEAMANA, COMUNA BRADU, DN 65, NR.2, JUDETUL ARGES', 'AG'));
        $this->assertSame(1.0, AddressNormalizer::similarity('CENTURA DE NORD DN7 KM541+100, ARAD', 'DN 7, KM. 541+100, CENTURA NORD, ARAD, JUDEȚUL ARAD', 'AR'));
        $this->assertSame(1.0, AddressNormalizer::similarity('Str. Spineni nr. 18A, sector 4', 'STR. SPINENI NR. 18 A, SECTORUL 4', 'B'));
        $this->assertGreaterThanOrEqual(0.9, AddressNormalizer::similarity('Bulevardul Basarabia 167 bis, București 030351', 'Sector 3, B-dul Basarabia, nr. 167 bis, București', 'B'));
        $this->assertSame(1.0, AddressNormalizer::similarity('Prelungirea Ghencea nr. 17, construcție C1, sector 6', 'PRELUNGIREA GHENCEA NR. 17, CLĂDIREA NR. C1, SECTOR 6', 'B'));
        $this->assertSame(1.0, AddressNormalizer::similarity('STR.MOȘOAIA NR.31,PARTER, SECTORUL4, BUCUREȘTI', 'STR. MOȘOAIA, NR. 31, SECTOR 4, BUCUREȘTI', 'B'));
        $this->assertSame(1.0, AddressNormalizer::similarity('BD. MĂRĂȘTI, NR. 59, HALA NR. 1, SECTORUL 1', 'B-DUL MĂRĂȘTI NR. 59, HALA 1, SECTOR 1', 'B'));
    }

    public function test_two_house_numbers_on_one_street_are_two_buildings(): void
    {
        $this->assertTrue(AddressNormalizer::houseNumbersDisagree('Șos. Chitilei nr. 131, sector 1', 'Șos. Chitilei nr. 295, sector 1', 'B'));
        $this->assertFalse(AddressNormalizer::houseNumbersDisagree('Str. Spineni nr. 18A, sector 4', 'STR. SPINENI NR. 18 A, SECTORUL 4', 'B'));
        $this->assertFalse(AddressNormalizer::houseNumbersDisagree('Strada Principală', 'Strada Principală nr. 5', 'CJ'));
        // A number in the street's own name is not a house number.
        $this->assertTrue(AddressNormalizer::houseNumbersDisagree('Str. Euro 85, nr. 160, Mărăcineni', 'Str. Euro 85 nr. 39-41, Mărăcineni', 'BZ'));
        $this->assertTrue(AddressNormalizer::houseNumbersDisagree('Aleea 1 Șimnic 11C, Craiova', 'Craiova, Aleea 1 Șimnic nr. 3 B', 'DJ'));
        $this->assertFalse(AddressNormalizer::houseNumbersDisagree('Str. 1 Decembrie 1918 nr. 143', 'Str. 1 Decembrie 1917 nr. 143', 'TL'));
        // The T of a plot ("T.29") that follows the house number is not a letter of it.
        $this->assertFalse(AddressNormalizer::houseNumbersDisagree('Calea Moldovei nr. 31, T.29-P.149/1/1, Focșani', 'Focșani, Calea Moldovei, T.29-P.149/1/1 nr. 31', 'VN'));
    }

    public function test_a_street_is_the_same_street_however_it_is_spelt_or_declined(): void
    {
        $this->assertFalse(AddressNormalizer::sameStreet('Str. Dezrobirii nr. 13, Târgu Mureș', 'Str. Toamnei nr. 13, Târgu Mureș', 'MS', ['Târgu Mureș']));
        $this->assertTrue(AddressNormalizer::sameStreet('Calea București nr. 24, Otopeni', 'Otopeni, Calea Bucureștilor nr. 24', 'IF', ['Otopeni']));
        $this->assertTrue(AddressNormalizer::sameStreet('Strada Fântânele nr. 43, Pașcani', 'Strada Fîntînele nr. 43, Pașcani', 'IS', ['Pașcani']));
        $this->assertTrue(AddressNormalizer::sameStreet('Com. Bradu, DN65B, nr. 2', 'Com. Bradu, Șos. DN 65 B nr. 2', 'AG', ['Bradu']));
        $this->assertNull(AddressNormalizer::sameStreet('Sat Șercaia, FN', 'Principala nr. FN, Șercaia', 'BV', ['Șercaia']));
    }

    public function test_a_sector_or_a_floor_area_is_not_a_house_number(): void
    {
        $this->assertSame(0.0, AddressNormalizer::similarity('Str. Răcari nr. 5, sector 3', 'Str. Răcari nr. 7, sector 3', 'B'));
        $this->assertSame(0.0, AddressNormalizer::similarity('Calea Giulești nr. 121D, sector 6', 'Calea Giulești nr. 121 B, sector 6', 'B'));
        $this->assertGreaterThanOrEqual(0.9, AddressNormalizer::similarity('Șos. Olteniței nr. 103, spațiu în suprafață de 110 mp', 'Șos. Olteniței nr. 103', 'B'));
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
