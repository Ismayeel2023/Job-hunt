<?php

use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('jobhunt:demo-clear', function () {
    DemoSeeder::clear();
    $this->info('Demo data removed.');
})->purpose('Remove the sample data created by DemoSeeder');

// Runs while `npm run start` (which includes schedule:work) is running.
Schedule::command('jobhunt:sync-mail')->hourly()->withoutOverlapping(30);
