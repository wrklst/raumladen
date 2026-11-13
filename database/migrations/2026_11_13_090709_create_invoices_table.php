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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('type')->index();
            $table->string('number')->unique();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('references_invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->date('issued_at')->index();
            $table->char('currency', 3)->default('EUR');
            $table->integer('total_net_cents');
            $table->integer('total_tax_cents');
            $table->integer('total_gross_cents');
            $table->json('snapshot');
            $table->string('pdf_path')->nullable();
            $table->foreignId('datev_export_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
