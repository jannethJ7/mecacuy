<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AjusteSistemaController extends Controller
{
    public function edit(): View
    {
        $config = [
            'modo_global' => $this->getConfig('modo_global', 'manual'),
            'stale_min' => (int) $this->getConfig('stale_min', 10),
            'retention_days' => (int) $this->getConfig('retention_days', 30),
            'zona_horaria_default' => $this->getConfig('zona_horaria_default', 'America/La_Paz'),
            'iot_ack_timeout_seg' => (int) $this->getConfig('iot_ack_timeout_seg', 20),
            'iot_max_intentos' => (int) $this->getConfig('iot_max_intentos', 3),
        ];

        return view('panel.ajustes.sistema', compact('config'));
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'modo_global' => ['required', Rule::in(['manual', 'automatico'])],
            'stale_min' => ['required', 'integer', 'min:1', 'max:1440'],
            'retention_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'zona_horaria_default' => ['required', 'timezone'],
            'iot_ack_timeout_seg' => ['required', 'integer', 'min:5', 'max:3600'],
            'iot_max_intentos' => ['required', 'integer', 'min:1', 'max:10'],
        ], [
            'modo_global.in' => 'El modo global debe ser manual o automático.',
            'stale_min.max' => 'El tiempo de conexión reciente no puede ser mayor a 1440 minutos.',
            'retention_days.max' => 'La retención no puede ser mayor a 3650 días.',
            'iot_ack_timeout_seg.min' => 'El tiempo de espera de ACK debe ser de al menos 5 segundos.',
            'iot_ack_timeout_seg.max' => 'El tiempo de espera de ACK no puede ser mayor a 3600 segundos.',
            'iot_max_intentos.max' => 'El número máximo de intentos no puede ser mayor a 10.',
        ]);

        DB::transaction(function () use ($data): void {
            $this->setConfig('modo_global', $data['modo_global']);
            $this->setConfig('stale_min', (int) $data['stale_min']);
            $this->setConfig('retention_days', (int) $data['retention_days']);
            $this->setConfig('zona_horaria_default', $data['zona_horaria_default']);
            $this->setConfig('iot_ack_timeout_seg', (int) $data['iot_ack_timeout_seg']);
            $this->setConfig('iot_max_intentos', (int) $data['iot_max_intentos']);

            if (Schema::hasTable('auditoria_eventos')) {
                DB::table('auditoria_eventos')->insert([
                    'actor_tipo' => 'user',
                    'actor_id' => auth()->id(),
                    'evento_tipo' => 'config_sistema.actualizada',
                    'entidad_tipo' => 'config_sistema',
                    'entidad_id' => null,
                    'data' => json_encode([
                        'modo_global' => $data['modo_global'],
                        'stale_min' => (int) $data['stale_min'],
                        'retention_days' => (int) $data['retention_days'],
                        'zona_horaria_default' => $data['zona_horaria_default'],
                        'iot_ack_timeout_seg' => (int) $data['iot_ack_timeout_seg'],
                        'iot_max_intentos' => (int) $data['iot_max_intentos'],
                    ]),
                    'creado_en' => now(),
                ]);
            }
        });

        return redirect()
            ->route('panel.ajustes.sistema')
            ->with('success', 'Configuración del sistema actualizada correctamente.');
    }

    private function getConfig(string $clave, mixed $default = null): mixed
    {
        $valor = DB::table('config_sistema')->where('clave', $clave)->value('valor');

        if (is_null($valor)) {
            return $default;
        }

        $decoded = json_decode($valor, true);

        return is_null($decoded) && json_last_error() !== JSON_ERROR_NONE
            ? $default
            : $decoded;
    }

    private function setConfig(string $clave, mixed $valor): void
    {
        DB::table('config_sistema')->updateOrInsert(
            ['clave' => $clave],
            [
                'valor' => json_encode($valor),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }
}
