<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What a copy costs the shop (net, in cents), and what it cost when an
     * order item was sold: the product's cost changes with every print run,
     * the margin of a past sale must not.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('cost_cents')->nullable()->after('price_cents');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedInteger('unit_cost_cents')->nullable()->after('unit_price_cents');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('cost_cents');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('unit_cost_cents');
        });
    }
};
