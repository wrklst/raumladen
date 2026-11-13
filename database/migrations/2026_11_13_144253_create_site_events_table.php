<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Anonymous visitor statistics: no address, no browser string, no id
     * that outlives a day (see App\Analytics\SiteVisits).
     */
    public function up(): void
    {
        Schema::create('site_events', function (Blueprint $table) {
            $table->id();
            $table->string('type', 16);
            $table->string('path');
            $table->string('locale', 8)->nullable();
            $table->string('visit', 16);
            $table->string('visit_key', 32);
            $table->string('landing_path')->nullable();
            $table->string('referrer_host', 190)->nullable();
            $table->string('utm_source', 128)->nullable();
            $table->string('utm_medium', 128)->nullable();
            $table->string('utm_campaign', 128)->nullable();
            $table->string('device', 8);
            $table->unsignedSmallInteger('seconds')->nullable();
            $table->timestamp('created_at')->index();

            $table->index(['created_at', 'visit']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_events');
    }
};
