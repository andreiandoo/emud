<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Order;
use App\Models\ShippingProvider;
use App\Shipping\ShipmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FanCourierGatewayTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_and_persists_a_fan_courier_awb(): void
    {
        Http::fake(['api.fancourier.ro/*' => Http::response(['shipments' => [['awbNumber' => '123456789', 'cost' => 24.5]]])]);
        $address = Address::create(['first_name' => 'Ion', 'last_name' => 'Pop', 'phone' => '0700000000', 'city' => 'Brașov', 'county' => 'Brașov', 'line_1' => 'Str. Test 1']);
        $order = Order::create(['number' => 'EM-AWB', 'shipping_address_id' => $address->id, 'customer_email' => 'ion@example.com', 'customer_phone' => '0700000000', 'grand_total' => 500, 'currency' => 'RON']);
        $provider = ShippingProvider::create(['code' => 'fan', 'name' => 'FAN', 'driver' => 'fan_courier', 'is_active' => true, 'credentials' => ['token' => 'token', 'client_id' => 'client']]);

        $shipment = app(ShipmentService::class)->createAwb($order, $provider, ['weight_kg' => 5, 'pieces' => 1]);

        $this->assertSame('123456789', $shipment->awb_number);
        $this->assertSame('processing', $order->refresh()->fulfillment_status);
    }
}
