<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An order paid online is placed when its payment is confirmed; until
     * then it is a draft that holds the stock while the customer pays.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('placed_at')->nullable()->after('status')->index();
        });

        DB::table('orders')->update(['placed_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('placed_at');
        });
    }
};
