<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('agenda:generar-diaria')
    ->dailyAt('07:00')
    ->timezone(config('app.timezone'))
    ->withoutOverlapping(5);

Schedule::command('turnos:preparar-recordatorios')
    ->cron(config('recordatorios.cron'))
    ->timezone(config('app.timezone'))
    ->name('uc27:seleccion-recordatorios')
    ->withoutOverlapping(5);
