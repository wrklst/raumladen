<?php

use App\Models\Product;

return fn (Product $product): GdImage => imagecreatetruecolor(1200, 630);
