<?php

namespace Database\Seeders;

use App\Enums\CatalogRightsClass;
use App\Enums\SupplierProtocol;
use App\Models\Supplier;
use App\Models\SupplierSyncSchedule;
use Illuminate\Database\Seeder;

class SupplierProfileSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedMahleTecCmd();
    }

    private function seedMahleTecCmd(): void
    {
        $supplier = Supplier::query()->firstOrCreate(
            ['code' => 'MAHLE_TECCMD'],
            [
                'name' => 'MAHLE TecCMD',
                'protocol' => SupplierProtocol::Sftp,
                'default_currency' => 'EUR',
                'timezone' => 'Europe/Berlin',
                'priority' => 30,
                'is_active' => false,
                'data_rights_class' => CatalogRightsClass::CommerceOnly,
                'allow_internal_data' => true,
                'allow_ecommerce_data' => true,
                'allow_derived_data' => false,
                'allow_api_redistribution' => false,
                'attribution_required' => false,
                'license_name' => 'MAHLE TecCMD customer access / contractual terms',
                'license_url' => 'https://www.mahle-aftermarket.com/eu/en/services/teccmd/',
                'legal_notes' => 'Official TecCMD documentation states that MAHLE customers can obtain current product information free of charge via web or SFTP and load the files into back-office systems linked to websites and sales platforms. Exact contractual permissions, redistribution rights, file layouts, remote paths and credentials must be confirmed with MAHLE before activation. Public API redistribution remains disabled by default.',
                'credentials' => [],
                'field_mapping' => [],
                'settings' => [
                    'profile' => 'mahle_teccmd',
                    'profile_status' => 'requires_customer_access_and_sample_files',
                    'feed_format' => 'csv',
                    'match_catalog_parts' => true,
                    'allow_unverified_sftp_host' => false,
                    'required_setup' => [
                        'Set SFTP host, username and password/private key.',
                        'Set and verify the MAHLE SFTP host fingerprint.',
                        'Set catalog_endpoint to the material-master file/path supplied by MAHLE.',
                        'Set price_endpoint to the price file/path supplied by MAHLE.',
                        'Set stock_endpoint to the stock/availability file/path supplied by MAHLE.',
                        'Map actual export headers after receiving sample files.',
                        'Review the customer agreement before enabling derived data or API redistribution.',
                    ],
                    'official_update_cadence' => [
                        'catalog' => 'daily material master',
                        'prices' => 'daily prices',
                        'stock' => 'hourly stock and availability',
                    ],
                    'documentation_url' => 'https://www.mahle-aftermarket.com/eu/en/services/teccmd/',
                ],
            ],
        );

        foreach ([
            'catalog' => ['cron' => '10 2 * * *', 'timezone' => 'Europe/Berlin'],
            'prices' => ['cron' => '40 1 * * *', 'timezone' => 'Europe/Berlin'],
            'stock' => ['cron' => '5 * * * *', 'timezone' => 'Europe/Berlin'],
        ] as $mode => $schedule) {
            SupplierSyncSchedule::query()->firstOrCreate(
                ['supplier_id' => $supplier->id, 'mode' => $mode],
                [
                    'cron_expression' => $schedule['cron'],
                    'timezone' => $schedule['timezone'],
                    'is_enabled' => false,
                ],
            );
        }
    }
}
