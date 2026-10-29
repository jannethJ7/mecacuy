<?php

namespace App\Console\Commands;

use App\Models\Modulo;
use App\Services\NivelAgua;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ConfigurarNivelAgua extends Command
{
    protected $signature = 'mecacuy:agua-configurar {modulo : Código del módulo}';
    protected $description = 'Agrupa el circuito probado de cuatro niveles en un único sensor S_NIVEL.';

    public function handle(NivelAgua $nivel): int
    {
        $modulo = Modulo::where('codigo', $this->argument('modulo'))->first();
        if (!$modulo) { $this->error('Módulo no encontrado.'); return self::FAILURE; }
        try {
            $nivel->validarConjunto($modulo->id, $modulo->sensores()->where('codigo', NivelAgua::CODIGO)->value('id'));
            $ruta = 'respaldos/nivel_agua_'.$modulo->id.'_'.now()->format('Ymd_His_u').'.json';
            Storage::disk('local')->put($ruta, json_encode([
                'sensores' => $modulo->sensores()->get()->toArray(),
                'reglas_automaticas' => DB::table('reglas_automaticas')->where('modulo_id', $modulo->id)->get()->toArray(),
                'estado_reglas' => DB::table('estado_reglas')->whereIn('regla_id', $modulo->reglasAutomaticas()->pluck('id'))->get()->toArray(),
                'reglas_alerta' => DB::table('reglas_alerta')->where('modulo_id', $modulo->id)->get()->toArray(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $resultado = $nivel->configurar($modulo);
            $this->info(json_encode($resultado, JSON_UNESCAPED_UNICODE));
            $this->info('Respaldo de configuración: '.Storage::disk('local')->path($ruta));
            $this->info('No se borraron lecturas históricas ni se enviaron órdenes a los actuadores.');
            return self::SUCCESS;
        } catch (ValidationException $e) {
            foreach ($e->errors() as $mensajes) foreach ($mensajes as $mensaje) $this->error($mensaje);
            return self::FAILURE;
        }
    }
}
