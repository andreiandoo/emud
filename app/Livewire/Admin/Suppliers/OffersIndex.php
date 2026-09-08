<?php

namespace App\Livewire\Admin\Suppliers;

use App\Commerce\ContributionMarginCalculator;
use App\Commerce\CurrencyConverter;
use App\Commerce\RetailPriceCalculator;
use App\Enums\ShippingClass;
use App\Models\Supplier;
use App\Models\SupplierOffer;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Landed cost and contribution for supplier offers.
 *
 * Exists so a buying decision can be checked against real numbers rather than
 * against a percentage in a spreadsheet: the unit price is only one of the inputs,
 * and on bulky 4x4 parts it is rarely the decisive one.
 */
#[Layout('layouts::admin')]
class OffersIndex extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $supplier = '';

    #[Url(except: '')]
    public string $shippingClass = '';

    #[Url(except: false)]
    public bool $onlyIncomplete = false;

    public function updated(string $property): void
    {
        if ($property !== 'page') {
            $this->resetPage();
        }
    }

    public function render(ContributionMarginCalculator $margins, RetailPriceCalculator $retail, CurrencyConverter $converter)
    {
        $offers = SupplierOffer::query()
            ->with(['supplierProduct.supplier', 'warehouse'])
            ->whereHas('supplierProduct', function ($query): void {
                $query->whereNull('discontinued_at')
                    ->when($this->supplier !== '', fn ($q) => $q->where('supplier_id', $this->supplier))
                    ->when($this->search !== '', function ($q): void {
                        $term = '%'.mb_strtolower(trim($this->search)).'%';
                        $q->where(fn ($inner) => $inner->whereRaw('lower(name) like ?', [$term])
                            ->orWhereRaw('lower(coalesce(supplier_sku, \'\')) like ?', [$term])
                            ->orWhereRaw('lower(coalesce(manufacturer_part_number, \'\')) like ?', [$term]));
                    });
            })
            ->when($this->shippingClass !== '', fn ($query) => $query->where('shipping_class', $this->shippingClass))
            ->orderByDesc('id')
            ->paginate(25);

        $rows = $offers->getCollection()->map(function (SupplierOffer $offer) use ($margins, $retail, $converter): array {
            // Priced at what we would actually ask for it: the supplier's recommended
            // price when it gave one, otherwise our own markup rule. Both are quoted in
            // the supplier's currency, so both are converted before being compared with
            // a landed cost that is already in the base currency.
            $recommended = $offer->recommended_retail_price !== null
                ? $converter->convert((float) $offer->recommended_retail_price, $offer->currency)
                : null;

            $sellingPrice = $recommended
                ? $recommended['amount']
                : ($offer->base_cost_net !== null ? $retail->fromCost((float) $offer->base_cost_net, $offer->vat_rate !== null ? (float) $offer->vat_rate : null) : null);

            return [
                'offer' => $offer,
                'selling_price' => $sellingPrice,
                'economics' => $sellingPrice !== null ? $margins->for($offer, $sellingPrice) : null,
            ];
        });

        return view('livewire.admin.suppliers.offers-index', [
            'offers' => $offers,
            'rows' => $this->onlyIncomplete
                ? $rows->filter(fn (array $row): bool => ($row['economics']['landed_cost_breakdown']['complete'] ?? false) === false)
                : $rows,
            'suppliers' => Supplier::query()->orderBy('name')->pluck('name', 'id'),
            'shippingClasses' => ShippingClass::cases(),
        ]);
    }
}
