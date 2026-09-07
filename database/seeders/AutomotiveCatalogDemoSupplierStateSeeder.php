<?php

namespace Database\Seeders;

use App\Models\SupplierProduct;
use Illuminate\Database\Seeder;

class AutomotiveCatalogDemoSupplierStateSeeder extends Seeder
{
    public function run(): void
    {
        SupplierProduct::query()
            ->where('external_id', 'like', 'SUP-DEMO-%')
            ->where('catalog_mapping_status', 'auto_mapped')
            ->update(['catalog_mapping_status' => 'mapped_auto']);
    }
}
