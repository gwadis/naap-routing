<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('documents:check-due-dates')->daily();
Schedule::command('documents:vpaa-alarm')->hourly();
Schedule::command('sla:monitor')->everyThirtyMinutes();

