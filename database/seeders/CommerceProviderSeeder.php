<?php

namespace Database\Seeders;

use App\Models\PaymentProvider;
use App\Models\ShippingMethod;
use App\Models\ShippingProvider;
use Illuminate\Database\Seeder;

class CommerceProviderSeeder extends Seeder
{
    public function run(): void
    {
        PaymentProvider::updateOrCreate(['code' => 'stripe'], ['name' => 'Stripe', 'driver' => 'stripe', 'position' => 10]);
        PaymentProvider::updateOrCreate(['code' => 'netopia'], ['name' => 'NETOPIA Payments', 'driver' => 'netopia', 'position' => 20]);

        $fan = ShippingProvider::updateOrCreate(['code' => 'fan-courier'], [
            'name' => 'FAN Courier', 'driver' => 'fan_courier',
            'settings' => ['base_url' => 'https://api.fancourier.ro', 'awb_path' => '/intern-awb', 'tracking_path' => '/reports/awb', 'service' => 'Standard'],
        ]);
        ShippingMethod::updateOrCreate(['code' => 'fan-standard'], [
            'shipping_provider_id' => $fan->id, 'name' => 'Curier FAN Standard', 'type' => 'courier',
            'base_price' => 25, 'currency' => 'RON', 'estimated_days_min' => 1, 'estimated_days_max' => 3,
        ]);
    }
}
