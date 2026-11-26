<?php

use App\Models\Product;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A book's authors are entries of their own, linked to it in an order.
     * Books so far named their authors as text: every name becomes an author
     * (once), linked to the books that name it.
     */
    public function up(): void
    {
        Schema::create('author_product', function (Blueprint $table) {
            $table->foreignId('author_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->primary(['author_id', 'product_id']);
        });

        Product::withTrashed()->get()->each(function (Product $product): void {
            if (! empty($product->specs['authors'])) {
                $product->syncAuthorNames(explode(',', $product->specs['authors']));
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('author_product');
    }
};
