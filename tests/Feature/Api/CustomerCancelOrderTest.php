<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerCancelOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_cancel_pending_order_and_restore_stock(): void
    {
        $customer = User::create([
            'name' => 'Rara Customer',
            'email' => 'rara@example.com',
            'password' => 'password123',
            'role' => 'customer',
        ]);

        $category = Category::create(['name' => 'Elektronik', 'slug' => 'elektronik']);
        $product = Product::create([
            'category_id' => $category->id,
            'sku' => 'SKU-101',
            'name' => 'Mouse Gaming',
            'slug' => 'mouse-gaming',
            'description' => 'Produk uji cancel',
            'price' => 200000,
            'stock' => 5,
            'is_active' => true,
        ]);

        $shipping = ShippingService::create([
            'courier' => 'JNE',
            'service' => 'REG',
            'eta' => '2-3 hari',
            'cost' => 15000,
            'is_active' => true,
        ]);

        $order = Order::create([
            'code' => 'ORD-20261127-TEST01',
            'user_id' => $customer->id,
            'shipping_service_id' => $shipping->id,
            'recipient_name' => 'Rara Customer',
            'phone' => '081234567890',
            'address' => 'Purwokerto',
            'shipping_courier' => 'JNE',
            'shipping_service' => 'REG',
            'shipping_eta' => '2-3 hari',
            'subtotal' => 400000,
            'shipping_cost' => 15000,
            'grand_total' => 415000,
            'status' => 'pending',
            'ordered_at' => now(),
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'price' => 200000,
            'quantity' => 2,
            'subtotal' => 400000,
        ]);

        Sanctum::actingAs($customer, ['customer']);

        $response = $this->patchJson("/api/v1/orders/{$order->id}/cancel");

        $response->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertSame(7, $product->fresh()->stock);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => 'IN',
            'quantity' => 2,
        ]);
    }

    public function test_customer_cannot_cancel_confirmed_order(): void
    {
        $customer = User::create([
            'name' => 'Rara Customer',
            'email' => 'rara@example.com',
            'password' => 'password123',
            'role' => 'customer',
        ]);

        $shipping = ShippingService::create([
            'courier' => 'JNE',
            'service' => 'REG',
            'eta' => '2-3 hari',
            'cost' => 15000,
            'is_active' => true,
        ]);

        $order = Order::create([
            'code' => 'ORD-20261127-TEST02',
            'user_id' => $customer->id,
            'shipping_service_id' => $shipping->id,
            'recipient_name' => 'Rara Customer',
            'phone' => '081234567890',
            'address' => 'Purwokerto',
            'shipping_courier' => 'JNE',
            'shipping_service' => 'REG',
            'shipping_eta' => '2-3 hari',
            'subtotal' => 200000,
            'shipping_cost' => 15000,
            'grand_total' => 215000,
            'status' => 'confirmed',
            'ordered_at' => now(),
        ]);

        Sanctum::actingAs($customer, ['customer']);

        $response = $this->patchJson("/api/v1/orders/{$order->id}/cancel");

        $response->assertStatus(409);
        $this->assertSame('confirmed', $order->fresh()->status);
    }
}