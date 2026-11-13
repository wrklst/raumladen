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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('number')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('pending')->index();
            $table->string('locale', 5);
            $table->char('currency', 3)->default('EUR');
            $table->string('email');
            $table->string('customer_group_code');
            $table->boolean('prices_include_tax')->default(true);
            $table->json('billing_address');
            $table->json('shipping_address');
            $table->char('country', 2)->index();
            $table->string('vat_id')->nullable();
            $table->boolean('vat_id_valid')->default(false);
            $table->string('tax_treatment');
            $table->string('tax_note')->nullable();
            $table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();
            $table->string('coupon_code')->nullable();
            $table->integer('subtotal_net_cents')->default(0);
            $table->integer('discount_cents')->default(0);
            $table->integer('shipping_net_cents')->default(0);
            $table->integer('shipping_tax_cents')->default(0);
            $table->decimal('shipping_tax_rate', 5, 2)->default(0);
            $table->integer('tax_cents')->default(0);
            $table->integer('total_cents')->default(0);
            $table->json('tax_summary')->nullable();
            $table->string('payment_gateway')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('customer_note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
