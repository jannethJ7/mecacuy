<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Panel\PanelController;
use App\Http\Controllers\Panel\ActuadorController;
use App\Http\Controllers\Panel\AjusteSistemaController;
use App\Http\Controllers\Panel\AlertaController;
use App\Http\Controllers\Panel\LecturaController;
use App\Http\Controllers\Panel\ModuloController;
use App\Http\Controllers\Panel\ProgramacionController;
use App\Http\Controllers\Panel\ReglaAutomaticaController;
use App\Http\Controllers\Panel\ReporteLecturaController;
use App\Http\Controllers\Panel\SensorController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('public.home'))->name('home');

Route::middleware(['auth'])->group(function () {

    Route::get('/dashboard', fn () => redirect()->route('panel.dashboard'))
        ->name('dashboard');

    Route::prefix('panel')->name('panel.')->group(function () {

        /*
        |--------------------------------------------------------------------------
        | Dashboard
        |--------------------------------------------------------------------------
        */
        Route::get('/', [PanelController::class, 'dashboard'])
            ->name('dashboard');

        /*
        |--------------------------------------------------------------------------
        | Módulos
        |--------------------------------------------------------------------------
        | admin: crea, edita y elimina.
        | operador: solo visualiza.
        | lector: sin acceso directo a esta sección.
        */
        // Primero van las rutas estáticas de administración, como /modulos/create.
        // Si se registran después de /modulos/{modulo}, Laravel interpreta "create"
        // como si fuera un ID de módulo y devuelve 404 por model binding.
        Route::resource('modulos', ModuloController::class)
            ->except(['index', 'show'])
            ->middleware('role:admin')
            ->parameters([
                'modulos' => 'modulo',
            ]);

        Route::resource('modulos', ModuloController::class)
            ->only(['index', 'show'])
            ->middleware('role:admin,operador')
            ->parameters([
                'modulos' => 'modulo',
            ]);

        /*
        |--------------------------------------------------------------------------
        | Sensores
        |--------------------------------------------------------------------------
        | admin: crea, edita y elimina.
        | operador: solo visualiza.
        | lector: sin acceso directo a esta sección.
        */
        Route::resource('sensores', SensorController::class)
            ->except(['index', 'show'])
            ->middleware('role:admin')
            ->parameters([
                'sensores' => 'sensor',
            ]);

        Route::resource('sensores', SensorController::class)
            ->only(['index', 'show'])
            ->middleware('role:admin,operador')
            ->parameters([
                'sensores' => 'sensor',
            ]);

        /*
        |--------------------------------------------------------------------------
        | Actuadores
        |--------------------------------------------------------------------------
        | admin: crea, edita y elimina.
        | operador: visualiza y opera manualmente.
        | lector: sin acceso directo a esta sección.
        */
        Route::resource('actuadores', ActuadorController::class)
            ->except(['index', 'show'])
            ->middleware('role:admin')
            ->parameters([
                'actuadores' => 'actuador',
            ]);

        Route::resource('actuadores', ActuadorController::class)
            ->only(['index', 'show'])
            ->middleware('role:admin,operador')
            ->parameters([
                'actuadores' => 'actuador',
            ]);

        /*
        |--------------------------------------------------------------------------
        | Control manual de actuadores
        |--------------------------------------------------------------------------
        */
        Route::post('actuadores/{actuador}/manual', [ActuadorController::class, 'manual'])
            ->middleware('role:admin,operador')
            ->name('actuadores.manual');

        /*
        |--------------------------------------------------------------------------
        | Lecturas
        |--------------------------------------------------------------------------
        */
        Route::get('lecturas', [LecturaController::class, 'index'])
            ->name('lecturas.index');

        /*
        |--------------------------------------------------------------------------
        | Alertas
        |--------------------------------------------------------------------------
        | Todos pueden consultar.
        | admin y operador pueden reconocer/cerrar.
        | solo admin puede eliminar.
        */
        Route::post('alertas/evaluar', [AlertaController::class, 'evaluarAhora'])
            ->middleware('role:admin,operador')
            ->name('alertas.evaluar');

        Route::resource('alertas', AlertaController::class)
            ->only(['index', 'show'])
            ->parameters([
                'alertas' => 'alerta',
            ]);

        Route::delete('alertas/{alerta}', [AlertaController::class, 'destroy'])
            ->middleware('role:admin')
            ->name('alertas.destroy');

        Route::patch('alertas/{alerta}/reconocer', [AlertaController::class, 'reconocer'])
            ->middleware('role:admin,operador')
            ->name('alertas.reconocer');

        Route::patch('alertas/{alerta}/cerrar', [AlertaController::class, 'cerrar'])
            ->middleware('role:admin,operador')
            ->name('alertas.cerrar');

        
        Route::prefix('automatizacion')
            ->name('automatizacion.')
            ->group(function () {

                
                Route::get('reglas/create', [ReglaAutomaticaController::class, 'create'])
                    ->middleware('role:admin')
                    ->name('reglas.create');

                Route::post('reglas', [ReglaAutomaticaController::class, 'store'])
                    ->middleware('role:admin')
                    ->name('reglas.store');

                Route::post('reglas/evaluar', [ReglaAutomaticaController::class, 'evaluarAhora'])
                    ->middleware('role:admin,operador')
                    ->name('reglas.evaluar');

                Route::get('reglas', [ReglaAutomaticaController::class, 'index'])
                    ->middleware('role:admin,operador')
                    ->name('reglas.index');

                Route::get('reglas/{regla}/edit', [ReglaAutomaticaController::class, 'edit'])
                    ->middleware('role:admin')
                    ->whereNumber('regla')
                    ->name('reglas.edit');

                Route::match(['put', 'patch'], 'reglas/{regla}', [ReglaAutomaticaController::class, 'update'])
                    ->middleware('role:admin')
                    ->whereNumber('regla')
                    ->name('reglas.update');

                Route::delete('reglas/{regla}', [ReglaAutomaticaController::class, 'destroy'])
                    ->middleware('role:admin')
                    ->whereNumber('regla')
                    ->name('reglas.destroy');

                Route::get('reglas/{regla}', [ReglaAutomaticaController::class, 'show'])
                    ->middleware('role:admin,operador')
                    ->whereNumber('regla')
                    ->name('reglas.show');

                
                Route::get('programaciones/create', [ProgramacionController::class, 'create'])
                    ->middleware('role:admin')
                    ->name('programaciones.create');

                Route::post('programaciones', [ProgramacionController::class, 'store'])
                    ->middleware('role:admin')
                    ->name('programaciones.store');

                Route::post('programaciones/evaluar', [ProgramacionController::class, 'evaluarAhora'])
                    ->middleware('role:admin,operador')
                    ->name('programaciones.evaluar');

                Route::get('programaciones', [ProgramacionController::class, 'index'])
                    ->middleware('role:admin,operador')
                    ->name('programaciones.index');

                Route::get('programaciones/{programacion}/edit', [ProgramacionController::class, 'edit'])
                    ->middleware('role:admin')
                    ->whereNumber('programacion')
                    ->name('programaciones.edit');

                Route::match(['put', 'patch'], 'programaciones/{programacion}', [ProgramacionController::class, 'update'])
                    ->middleware('role:admin')
                    ->whereNumber('programacion')
                    ->name('programaciones.update');

                Route::delete('programaciones/{programacion}', [ProgramacionController::class, 'destroy'])
                    ->middleware('role:admin')
                    ->whereNumber('programacion')
                    ->name('programaciones.destroy');

                Route::get('programaciones/{programacion}', [ProgramacionController::class, 'show'])
                    ->middleware('role:admin,operador')
                    ->whereNumber('programacion')
                    ->name('programaciones.show');
            });

        
        Route::get('ajustes/sistema', [AjusteSistemaController::class, 'edit'])
            ->middleware('role:admin,operador')
            ->name('ajustes.sistema');

        Route::put('ajustes/sistema', [AjusteSistemaController::class, 'update'])
            ->middleware('role:admin,operador')
            ->name('ajustes.sistema.update');

        
        Route::get('reportes/lecturas', [ReporteLecturaController::class, 'index'])
            ->name('reportes.lecturas');
    });

    
    Route::get('/profile', [ProfileController::class, 'edit'])
        ->name('profile.edit');

    Route::patch('/profile', [ProfileController::class, 'update'])
        ->name('profile.update');

    Route::post('/profile/iot-keys/{modulo}/regenerar', [ProfileController::class, 'regenerateIotKey'])
        ->middleware('role:admin')
        ->name('profile.iot-keys.regenerate');

    Route::delete('/profile', [ProfileController::class, 'destroy'])
        ->name('profile.destroy');
});

require __DIR__ . '/auth.php';
