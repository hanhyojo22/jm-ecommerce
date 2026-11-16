<?php

namespace Tests\Unit;

use App\Models\SellerOrder;
use PHPUnit\Framework\TestCase;

class SellerOrderStatusLabelTest extends TestCase
{
    public function test_delivery_orders_read_like_food_delivery(): void
    {
        $this->assertSame(
            ['Order placed', 'Accepted', 'Preparing', 'Out for delivery', 'Delivered', 'Cancelled'],
            array_map(fn (string $status): string => SellerOrder::statusLabel($status, 'delivery'), ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled']),
        );
    }

    public function test_pickup_orders_name_the_counter_steps(): void
    {
        $this->assertSame('Ready for pickup', SellerOrder::statusLabel('shipped', 'pickup'));
        $this->assertSame('Picked up', SellerOrder::statusLabel('delivered', 'pickup'));
    }

    public function test_an_order_without_a_fulfillment_method_reads_as_delivery(): void
    {
        $this->assertSame('Out for delivery', SellerOrder::statusLabel('shipped', null));
    }
}
