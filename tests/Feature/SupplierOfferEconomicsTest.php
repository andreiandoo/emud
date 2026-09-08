<?php

namespace Tests\Feature;

use App\Commerce\ContributionMarginCalculator;
use App\Commerce\CurrencyConverter;
use App\Commerce\ExchangeRateImporter;
use App\Commerce\LandedCostCalculator;
use App\Enums\ShippingClass;
use App\Models\ExchangeRate;
use App\Models\Supplier;
use App\Models\SupplierOffer;
use App\Models\SupplierProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class SupplierOfferEconomicsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'emud.catalog.default_currency' => 'RON',
            'emud.catalog.default_vat_rate' => 21,
            'emud.pricing.margins' => [
                'payment_fee_percent' => 2.0,
                'payment_fee_fixed' => 0.0,
                'return_rate_percent' => 10.0,
                'return_handling_cost' => 10.0,
                'bulky_return_multiplier' => 3.0,
                'warranty_reserve_percent' => 0.0,
            ],
        ]);

        ExchangeRate::query()->create([
            'base_currency' => 'EUR', 'quote_currency' => 'RON',
            'rate' => 5.0, 'rate_date' => now()->subDay()->toDateString(), 'source' => 'test',
        ]);
    }

    public function test_a_missing_rate_is_never_treated_as_a_rate_of_one(): void
    {
        $converter = app(CurrencyConverter::class);

        $this->assertNull($converter->convert(100, 'USD', 'RON'), 'No rate must mean "cannot compare", not "one to one".');
        $this->assertSame(500.0, $converter->convert(100, 'EUR', 'RON')['amount']);
    }

    public function test_a_published_rate_is_inverted_and_crossed_when_needed(): void
    {
        ExchangeRate::query()->create([
            'base_currency' => 'PLN', 'quote_currency' => 'RON',
            'rate' => 1.25, 'rate_date' => now()->subDay()->toDateString(), 'source' => 'test',
        ]);

        $converter = app(CurrencyConverter::class);

        // RON -> EUR is never published; it is the inverse of the EUR rate.
        $this->assertSame(0.2, round($converter->convert(1, 'RON', 'EUR')['amount'], 4));
        // PLN -> EUR crosses through RON: 1 PLN = 1.25 RON = 0.25 EUR.
        $this->assertSame(0.25, round($converter->convert(1, 'PLN', 'EUR')['amount'], 4));
    }

    public function test_a_weekend_import_still_finds_the_last_published_rate(): void
    {
        $converter = app(CurrencyConverter::class);

        $this->assertSame(500.0, $converter->convert(100, 'EUR', 'RON', now())['amount']);
        $this->assertNull(
            $converter->convert(100, 'EUR', 'RON', now()->subYear()),
            'A rate published later must not be applied to an earlier date.',
        );
    }

    public function test_landed_cost_adds_every_fulfilment_charge_in_base_currency(): void
    {
        $offer = $this->offer(['cost_price' => 100, 'currency' => 'EUR', 'dropship_fee' => 5, 'handling_fee' => 2, 'shipping_cost_estimate' => 8]);

        $landed = app(LandedCostCalculator::class)->for($offer);

        // 100 EUR product + 5 + 2 + 8 EUR of charges, all at 5 RON to the euro.
        $this->assertSame(500.0, $landed['product_cost']);
        $this->assertSame(25.0, $landed['dropship_fee']);
        $this->assertSame(575.0, $landed['total']);
        $this->assertSame('RON', $landed['currency']);
        $this->assertTrue($landed['complete']);
    }

    public function test_per_order_charges_are_spread_across_the_quantity(): void
    {
        $offer = $this->offer(['cost_price' => 100, 'currency' => 'RON', 'dropship_fee' => 50, 'shipping_cost_estimate' => 0]);

        $single = app(LandedCostCalculator::class)->for($offer, 1);
        $ten = app(LandedCostCalculator::class)->for($offer, 10);

        $this->assertSame(150.0, $single['unit_landed_cost'], 'A fifty lei fee ruins a single unit.');
        $this->assertSame(105.0, $ten['unit_landed_cost'], 'The same fee is marginal across ten.');
    }

    public function test_an_incomplete_landed_cost_says_so_instead_of_pretending(): void
    {
        $offer = $this->offer(['cost_price' => 100, 'currency' => 'USD']);

        $landed = app(LandedCostCalculator::class)->for($offer);

        $this->assertFalse($landed['complete']);
        $this->assertContains('fx_rate', $landed['missing']);
        $this->assertContains('freight', $landed['missing']);
    }

    public function test_contribution_is_lower_than_gross_margin_once_fees_and_returns_are_counted(): void
    {
        $offer = $this->offer(['cost_price' => 600, 'currency' => 'RON', 'shipping_cost_estimate' => 60, 'vat_rate' => 21]);

        $result = app(ContributionMarginCalculator::class)->for($offer, sellingPriceGross: 1210.0);

        // 1210 gross at 21% VAT is 1000 net; gross margin is the naive 400.
        $this->assertSame(1000.0, $result['net_revenue']);
        $this->assertSame(400.0, $result['gross_margin']);
        $this->assertSame(40.0, $result['gross_margin_percent']);

        // Contribution also carries freight, the payment fee and the return reserve.
        $this->assertLessThan($result['gross_margin'], $result['contribution']);
        $this->assertSame(24.2, $result['payment_fee']);
        $this->assertSame(7.0, $result['return_reserve']);
        $this->assertSame(308.8, $result['contribution']);
    }

    public function test_a_bulky_class_is_penalised_for_its_reverse_freight(): void
    {
        $attributes = ['cost_price' => 600, 'currency' => 'RON', 'shipping_cost_estimate' => 60, 'vat_rate' => 21];

        $parcel = app(ContributionMarginCalculator::class)
            ->for($this->offer([...$attributes, 'shipping_class' => ShippingClass::SmallParcel]), 1210.0);
        $oversize = app(ContributionMarginCalculator::class)
            ->for($this->offer([...$attributes, 'shipping_class' => ShippingClass::Oversize], code: 'BULKY'), 1210.0);

        $this->assertGreaterThan($parcel['return_reserve'], $oversize['return_reserve']);
        $this->assertLessThan($parcel['contribution'], $oversize['contribution']);
    }

    public function test_a_supplier_restocking_fee_reduces_contribution(): void
    {
        $attributes = ['cost_price' => 600, 'currency' => 'RON', 'shipping_cost_estimate' => 60, 'vat_rate' => 21];

        $lenient = app(ContributionMarginCalculator::class)->for($this->offer($attributes), 1210.0);
        $strict = app(ContributionMarginCalculator::class)
            ->for($this->offer($attributes, code: 'STRICT', supplier: ['restocking_fee_percent' => 20]), 1210.0);

        $this->assertLessThan($lenient['contribution'], $strict['contribution']);
    }

    public function test_the_national_bank_document_is_parsed_into_per_unit_rates(): void
    {
        $xml = <<<'XML'
        <DataSet xmlns="http://www.bnr.ro/xsd">
          <Body>
            <OrigCurrency>RON</OrigCurrency>
            <Cube date="2026-09-08">
              <Rate currency="EUR">4.9755</Rate>
              <Rate currency="HUF" multiplier="100">1.2634</Rate>
              <Rate currency="XX">0</Rate>
            </Cube>
          </Body>
        </DataSet>
        XML;

        $result = app(ExchangeRateImporter::class)->importFromXml($xml);

        $this->assertSame('2026-09-08', $result['date']);
        $this->assertSame(2, $result['imported']);
        $this->assertSame(1, $result['skipped']);
        $this->assertSame('4.97550000', ExchangeRate::query()->where('base_currency', 'EUR')->where('rate_date', '2026-09-08')->sole()->rate);
        // A multiplier of 100 means the document quotes 100 forint, not one.
        $this->assertSame('0.01263400', ExchangeRate::query()->where('base_currency', 'HUF')->where('rate_date', '2026-09-08')->sole()->rate);
    }

    public function test_a_broken_rate_document_fails_loudly(): void
    {
        $this->expectException(RuntimeException::class);

        app(ExchangeRateImporter::class)->importFromXml('<DataSet><Body></Body></DataSet>');
    }

    /** @param array<string, mixed> $attributes */
    private function offer(array $attributes, string $code = 'ECON', array $supplier = []): SupplierOffer
    {
        $supplierModel = Supplier::query()->create([
            'name' => "Economics {$code}",
            'code' => $code,
            'protocol' => 'json',
            ...$supplier,
        ]);

        $product = SupplierProduct::query()->create([
            'supplier_id' => $supplierModel->id,
            'external_id' => 'SKU-'.$code,
            'name' => 'Produs test',
        ]);

        $offer = SupplierOffer::query()->create(['supplier_product_id' => $product->id, ...$attributes]);

        return $offer->load('supplierProduct.supplier');
    }
}
