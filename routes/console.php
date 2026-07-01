<?php

use App\Services\GestorComandosIot;
use App\Services\MotorProgramaciones;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

use App\Models\Lectura;
use Illuminate\Support\Carbon;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('mecacuy:programaciones-ejecutar {--modulo=}', function (MotorProgramaciones $motor) {
    $moduloId = $this->option('modulo');

    $resultado = $moduloId
        ? $motor->evaluarModulo((int) $moduloId)
        : $motor->evaluarTodos();

    $this->info(json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    return ($resultado['ok'] ?? false) ? 0 : 1;
})->purpose('Evalúa programaciones de MecaCuy y crea comandos IoT cuando corresponde.');


Artisan::command('mecacuy:comandos-depurar {--modulo=}', function (GestorComandosIot $gestor) {
    $moduloId = $this->option('modulo');

    $resultado = $moduloId
        ? $gestor->depurarModulo((int) $moduloId)
        : $gestor->depurarTodos();

    $this->info(json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    return ($resultado['ok'] ?? false) ? 0 : 1;
})->purpose('Depura comandos IoT expirados o fallidos por falta de ACK.');

Schedule::command('mecacuy:programaciones-ejecutar')
    ->everyMinute()
    ->withoutOverlapping();


Schedule::command('mecacuy:comandos-depurar')
    ->everyMinute()
    ->withoutOverlapping();
Artisan::command('mecacuy:lecturas-limpiar {--dias=0}', function () {
    $dias = max(1, (int) $this->option('dias'));

    $limite = Carbon::now()->subDays($dias);

    $eliminadas = Lectura::where('medido_en', '<', $limite)->delete();

    $this->info("Lecturas eliminadas: {$eliminadas}");
    $this->info("Se conservaron las lecturas de los últimos {$dias} día(s).");

    return 0;
})->purpose('Elimina lecturas antiguas para evitar crecimiento excesivo de la base de datos.');
Schedule::command('mecacuy:lecturas-limpiar --dias=2')
    ->dailyAt('03:00')
    ->withoutOverlapping();