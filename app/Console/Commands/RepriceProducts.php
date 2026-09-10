<?php

namespace App\Console\Commands;

use App\Commerce\Repricer;
use App\Enums\PriceChangeStatus;
use App\Enums\PricingMode;
use App\Models\Product;
use App\Models\SupplierProduct;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class RepriceProducts extends Command
{
    protected $signature = 'pricing:reprice
        {--product=* : Reprice only these product ids}
        {--all : Reprice every automatically priced product sold through suppliers}';

    protected $description = 'Recompute shelf prices from the landed cost of the offer each product would be fulfilled from.';

    public function handle(Repricer $repricer): int
    {
        $ids = array_filter(array_map('intval', (array) $this->option('product')));

        if ($ids === [] && ! $this->option('all')) {
            $this->error('Specifică --all sau cel puțin un --product=ID.');

            return self::INVALID;
        }

        $tally = ['applied' => 0, 'pending' => 0, 'unchanged' => 0];

        Product::query()
            ->where('pricing_mode', PricingMode::Auto->value)
            ->whereIn('id', SupplierProduct::query()->select('product_id')->whereNotNull('product_id'))
            ->when($ids !== [], fn ($query) => $query->whereIn('id', $ids))
            ->chunkById(200, function (Collection $products) use ($repricer, &$tally): void {
                foreach ($products as $product) {
                    $changes = collect($repricer->reprice($product, 'scheduled'));

                    if ($changes->isEmpty()) {
                        $tally['unchanged']++;

                        continue;
                    }

                    $tally['applied'] += $changes->where('status', PriceChangeStatus::Applied)->count();
                    $tally['pending'] += $changes->where('status', PriceChangeStatus::Pending)->count();
                }
            });

        $this->info("Prețuri aplicate: {$tally['applied']} · de aprobat: {$tally['pending']} · neschimbate: {$tally['unchanged']}");

        return self::SUCCESS;
    }
}
