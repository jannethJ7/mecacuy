<?php

namespace App\Console\Commands;

use App\Models\Modulo;
use App\Models\Sensor;
use App\Services\SupervisionCalefaccion;
use Illuminate\Console\Command;

class ConfigurarSupervisionCalefaccion extends Command
{
    protected $signature = 'mecacuy:calefaccion-configurar {modulo : Código del módulo} {--actuador= : Código del calefactor} {--gpio=33 : Entrada ADC1 libre}';
    protected $description = 'Registra la supervisión ACS712 pendiente de calibración, sin cambiar el estado del calefactor.';

    public function handle(): int
    {
        $modulo = Modulo::where('codigo', $this->argument('modulo'))->first();
        if (!$modulo) {
            $this->error('Módulo no encontrado.');
            return self::FAILURE;
        }
        $actuador = $modulo->actuadores()->when($this->option('actuador'),
            fn ($q) => $q->where('codigo', $this->option('actuador')),
            fn ($q) => $q->whereIn('codigo', ['D_CALEF', 'D_HEAT', 'D_RESISTENCIA', 'D_CALEFACTOR']))->get();
        if ($actuador->count() !== 1) {
            $this->error('Debe identificar un único calefactor con --actuador=CODIGO.');
            return self::FAILURE;
        }
        $gpio = filter_var($this->option('gpio'), FILTER_VALIDATE_INT);
        if (!in_array($gpio, [32, 33, 34, 35, 36, 39], true)) {
            $this->error('La medición requiere una entrada ADC1 del ESP32 clásico.');
            return self::FAILURE;
        }
        $sensor = $modulo->sensores()->where('codigo', 'S_CONF_CALEF')->first();
        if ($sensor) {
            $this->info('S_CONF_CALEF ya existe. Se conserva su configuración y calibración.');
            return self::SUCCESS;
        }
        if ($modulo->sensores()->where('gpio_pin', $gpio)->exists() || $modulo->actuadores()->where('gpio_pin', $gpio)->exists()) {
            $this->error('GPIO ocupado en este módulo. No se modificó la configuración.');
            return self::FAILURE;
        }
        Sensor::create([
            'modulo_id' => $modulo->id, 'codigo' => 'S_CONF_CALEF',
            'nombre' => 'Verificación eléctrica de calefacción', 'tipo' => SupervisionCalefaccion::TIPO,
            'unidad' => null, 'gpio_pin' => $gpio, 'activo' => true,
            'meta' => SupervisionCalefaccion::predeterminados($actuador->first()->codigo),
        ]);
        $this->info('Sensor registrado. Instale, pruebe y calibre antes de habilitar la confirmación desde Editar sensor.');
        return self::SUCCESS;
    }
}
