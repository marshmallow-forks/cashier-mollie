<?php

namespace Laravel\Cashier\Tests\Order;

use Laravel\Cashier\Order\Order;

class FakeOrderModelClass extends Order
{
    public $table = 'orders';
}
