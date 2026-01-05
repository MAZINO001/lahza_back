<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Artisan::command('lahzafresh', function () {
//     $this->call('migrate:fresh');
//     $this->call('db:seed');
//     $this->info('Database refreshed and seeded successfully!');
// })->purpose('Refresh and seed the database');

Schedule::command('ai:run-daily-summary')->dailyAt('00:01');