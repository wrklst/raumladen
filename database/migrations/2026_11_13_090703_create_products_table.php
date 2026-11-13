<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('type')->default('book')->index();
            $table->string('sku')->nullable()->unique();
            $table->json('slug');
            $table->json('title');
            $table->json('description')->nullable();
            $table->string('tax_class')->default('reduced');
            $table->unsignedInteger('price_cents');
            $table->char('currency', 3)->default('EUR');
            $table->integer('stock')->default(0);
            $table->boolean('track_stock')->default(true);
            $table->unsignedInteger('weight_grams')->nullable();
            $table->json('images')->nullable();
            $table->json('specs')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
