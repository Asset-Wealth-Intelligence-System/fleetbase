<?php

namespace Fleetbase\EnergyOps\Observers;

use Fleetbase\EnergyOps\Models\Product;
use Illuminate\Support\Str;

class ProductObserver
{
    public function creating(Product $product): void
    {
        if ($product->code) {
            $product->code = Str::upper($product->code);
        }
        if (!$product->skill && $product->code) {
            $product->skill = Str::slug($product->code, '_');
        }
    }
}
