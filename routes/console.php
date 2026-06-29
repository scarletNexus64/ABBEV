<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Housekeeping des uploads Bunny (lignes bloquées + fichiers temporaires orphelins).
Schedule::command('bunny:uploads:cleanup')->hourly();
