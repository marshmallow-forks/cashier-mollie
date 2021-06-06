<?php

namespace Laravel\Cashier\Tests\Order;

use Laravel\Cashier\Order\OrderItem;

class FakeOrderItemModelClass extends OrderItem
{
    public $table = 'order_items';
}
