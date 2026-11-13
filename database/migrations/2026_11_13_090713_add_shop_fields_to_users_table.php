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
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
            $table->string('role')->default('customer')->index()->after('email');
            $table->foreignId('customer_group_id')->nullable()->after('role')->constrained()->nullOnDelete();
            $table->string('locale', 5)->nullable()->after('customer_group_id');
            $table->string('company')->nullable()->after('locale');
            $table->string('vat_id')->nullable()->after('company');
            $table->timestamp('vat_id_validated_at')->nullable()->after('vat_id');
            $table->string('phone')->nullable()->after('vat_id_validated_at');
            $table->json('billing_address')->nullable()->after('phone');
            $table->json('shipping_address')->nullable()->after('billing_address');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_group_id');
            $table->dropColumn([
                'role', 'locale', 'company', 'vat_id', 'vat_id_validated_at',
                'phone', 'billing_address', 'shipping_address',
            ]);
            $table->string('password')->nullable(false)->change();
        });
    }
};
