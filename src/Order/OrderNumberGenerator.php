<?php

namespace Laravel\Cashier\Order;

use Laravel\Cashier\Cashier;

class OrderNumberGenerator
{
    protected $offset;

    /**
     * OrderNumberGenerator constructor.
     */
    public function __construct()
    {
        $this->offset = config('cashier.order_number_generator.offset');
    }

    /**
     * Generate an order reference.
     *
     * @return string
     */
    public function generate()
    {
        $orderModel = Cashier::$orderModel;
        $number = str_pad(
            $this->offset + $orderModel::count() + 1,
            8,
            '0',
            STR_PAD_LEFT
        );

        $numbers = str_split($number, 4);

        return implode('-', [
            now()->year,
            $numbers[0],
            $numbers[1],
        ]);
    }
}
