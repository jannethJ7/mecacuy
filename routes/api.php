<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Iot\V1\ControladorIot;

Route::prefix('iot/v1')
    ->middleware('iot.modulo')
    ->group(function () {
        Route::post('/lecturas', [ControladorIot::class, 'guardarLecturas']); // ESP32 -> server
        Route::get('/sync',      [ControladorIot::class, 'sync']);           // ESP32 <- estado + comando
        Route::post('/ack',      [ControladorIot::class, 'ack']);            // ESP32 -> confirmación + reportes
    });