<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customers_cannot_open_the_admin_area(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $this->actingAs($user)->get('/admin')->assertForbidden();
    }

    public function test_admins_can_open_the_admin_area(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user)->get('/admin')->assertOk();
    }
}
