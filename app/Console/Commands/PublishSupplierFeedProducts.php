<?php

namespace App\Console\Commands;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Console\Command;

/**
 * Products created from a feed land in review on purpose: nobody wants a supplier's catalogue
 * going live unread. Releasing them one by one through the admin is fine for a handful and
 * hopeless for a whole feed, so this is the bulk form of that same decision.
 */
class PublishSupplierFeedProducts extends Command
{
    protected $signature = 'suppliers:publish-feed-products
        {supplier : Codul furnizorului}
        {--limit=1000 : Câte produse se publică într-o rulare}
        {--dry-run : Arată ce s-ar publica, fără să schimbe nimic}';

    protected $description = 'Publică produsele aflate în review pe care le-a creat feed-ul unui furnizor.';

    public function handle(): int
    {
        $code = (string) $this->argument('supplier');
        $supplier = Supplier::query()->where('code', $code)->first();

        if (! $supplier) {
            $this->error("Furnizorul {$code} nu există.");

            return self::FAILURE;
        }

        // The importer stamps the origin into metadata, which is the only thing tying a review
        // product back to the feed that created it; matching on brand or name would sweep up
        // products somebody added by hand.
        $products = Product::query()
            ->where('status', ProductStatus::Review)
            ->where('metadata->created_from_supplier', $supplier->code)
            ->orderBy('id')
            ->limit(max(1, (int) $this->option('limit')))
            ->get(['id', 'name']);

        if ($products->isEmpty()) {
            $this->info("Niciun produs în review creat de {$supplier->code}.");

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->table(['ID', 'Nume'], $products->map(
                static fn (Product $product): array => [$product->id, $product->name],
            )->all());
            $this->info("{$products->count()} produs(e) s-ar publica.");

            return self::SUCCESS;
        }

        // Ids rather than the query itself: Postgres has no UPDATE ... LIMIT, and the rows are
        // already loaded for the dry-run path anyway.
        $published = Product::query()->whereKey($products->modelKeys())->update([
            'status' => ProductStatus::Active,
            'published_at' => now(),
        ]);

        $this->info("{$published} produs(e) publicate pentru {$supplier->code}.");

        return self::SUCCESS;
    }
}
