<?php

namespace App\Checkout;

use App\Models\Product;
use RuntimeException;

class OutOfStockException extends RuntimeException
{
    public function __construct(public readonly Product $product)
    {
        parent::__construct("Product [{$product->id}] has insufficient stock.");
    }
}
