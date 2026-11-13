<?php

namespace App\Cart;

use App\Models\Product;

final readonly class CartLine
{
    public function __construct(
        public Product $product,
        public int $quantity,
    ) {}
}
