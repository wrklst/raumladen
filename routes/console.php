<?php

use App\Models\SiteEvent;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('orders:cancel-unpaid')->daily();

// Visitor statistics older than thirteen months are deleted.
Schedule::command('model:prune', ['--model' => [SiteEvent::class]])->daily();
