<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ActividadRecienteService
{
    public function paraDashboard(int $limite = 10): Collection
    {
        return $this->construirFeed(null, $limite);
    }

    public function paraModulo(int $moduloId, int $limite = 10): Collection
    {
        return $this->construirFeed($moduloId, $limite);
    }

    private function construirFeed(?int $moduloId, int $limite): Collection
    {
        $limiteConsulta = max($limite * 3, 15);

        return collect()
            ->merge($this->actuaciones($moduloId, $limiteConsulta))
            ->merge($this->comandos($moduloId, $limiteConsulta))
            ->merge($this->alertas($moduloId, $limiteConsulta))
            ->merge($this->auditoria($moduloId, $limiteConsulta))
            ->filter(fn (array $item) => !empty($item['fecha']))
            ->sortByDesc(fn (array $item) => $item['fecha_ts'])
            ->values()
            ->take($limite);
    }

    private function actuaciones(?int $moduloId, int $limite): Collection
    {
        if (!Schema::hasTable('actuaciones')) {
            return collect();
        }

        $query = DB::table('actuaciones')
            ->leftJoin('actuadores', 'actuadores.id', '=', 'actuaciones.actuador_id')
            ->leftJoin('modulos', 'modulos.id', '=', 'actuaciones.modulo_id')
            ->select([
                'actuaciones.id',
                'actuaciones.modulo_id',
                'actuaciones.actuador_id',
                'actuaciones.origen',
                'actuaciones.estado_nuevo',
                'actuaciones.motivo',
                'actuaciones.ejecutado_en',
                'actuadores.codigo as actuador_codigo',
                'actuadores.nombre as actuador_nombre',
                'actuadores.tipo as actuador_tipo',
                'modulos.codigo as modulo_codigo',
                'modulos.nombre as modulo_nombre',
            ])
            ->orderByDesc('actuaciones.ejecutado_en')
            ->limit($limite);

        if ($moduloId) {
            $query->where('actuaciones.modulo_id', $moduloId);
        }

        return $query->get()->map(function ($row) {
            $estadoNuevo = $this->json($row->estado_nuevo);
            $motivo = $this->json($row->motivo);
            $on = data_get($estadoNuevo, 'on');
            $accion = $on === true ? 'encendido' : ($on === false ? 'apagado' : 'actualizado');
            $actuador = $row->actuador_nombre ?: ($row->actuador_codigo ?: 'Actuador');
            $origen = $this->origenLegible($row->origen);

            $detalle = trim(($row->modulo_codigo ?: 'Módulo') . ' · ' . $origen);
            $motivoTexto = $this->motivoLegible($motivo);
            if ($motivoTexto) {
                $detalle .= ' · ' . $motivoTexto;
            }

            return $this->item([
                'tipo' => 'actuacion',
                'titulo' => $actuador . ' ' . $accion,
                'detalle' => $detalle,
                'fecha' => $row->ejecutado_en,
                'estado' => strtoupper($accion),
                'severidad' => 'info',
                'icono' => $this->iconoActuador($row->actuador_codigo, $row->actuador_tipo),
                'modulo_id' => $row->modulo_id,
                'modulo_codigo' => $row->modulo_codigo,
                'entidad_id' => $row->id,
                'url_tipo' => 'actuadores',
            ]);
        });
    }

    private function comandos(?int $moduloId, int $limite): Collection
    {
        if (!Schema::hasTable('comandos_iot')) {
            return collect();
        }

        $query = DB::table('comandos_iot')
            ->leftJoin('actuadores', 'actuadores.id', '=', 'comandos_iot.actuador_id')
            ->leftJoin('modulos', 'modulos.id', '=', 'comandos_iot.modulo_id')
            ->select([
                'comandos_iot.id',
                'comandos_iot.modulo_id',
                'comandos_iot.actuador_id',
                'comandos_iot.tipo',
                'comandos_iot.estado',
                'comandos_iot.nonce',
                'comandos_iot.intentos',
                'comandos_iot.enviado_en',
                'comandos_iot.confirmado_en',
                'comandos_iot.ultimo_error',
                'comandos_iot.created_at',
                'comandos_iot.updated_at',
                'actuadores.codigo as actuador_codigo',
                'actuadores.nombre as actuador_nombre',
                'actuadores.tipo as actuador_tipo',
                'modulos.codigo as modulo_codigo',
            ])
            ->orderByDesc('comandos_iot.updated_at')
            ->limit($limite);

        if ($moduloId) {
            $query->where('comandos_iot.modulo_id', $moduloId);
        }

        return $query->get()->map(function ($row) {
            $fecha = $row->confirmado_en ?: ($row->enviado_en ?: ($row->updated_at ?: $row->created_at));
            $actuador = $row->actuador_nombre ?: ($row->actuador_codigo ?: 'ESP32');
            $estado = strtoupper((string) $row->estado);
            $detalle = ($row->modulo_codigo ?: 'Módulo') . ' · ' . $this->estadoComandoLegible($row->estado);
            $detalle .= ' · intento ' . ((int) $row->intentos);

            if ($row->ultimo_error) {
                $detalle .= ' · ' . $row->ultimo_error;
            }

            return $this->item([
                'tipo' => 'comando',
                'titulo' => 'Comando ' . $row->tipo . ' para ' . $actuador,
                'detalle' => $detalle,
                'fecha' => $fecha,
                'estado' => $estado,
                'severidad' => in_array($row->estado, ['fallido', 'expirado'], true) ? 'critico' : 'info',
                'icono' => in_array($row->estado, ['fallido', 'expirado'], true) ? 'ri-error-warning-line' : 'ri-send-plane-line',
                'modulo_id' => $row->modulo_id,
                'modulo_codigo' => $row->modulo_codigo,
                'entidad_id' => $row->id,
                'url_tipo' => 'actuadores',
            ]);
        });
    }

    private function alertas(?int $moduloId, int $limite): Collection
    {
        if (!Schema::hasTable('alertas')) {
            return collect();
        }

        $query = DB::table('alertas')
            ->leftJoin('modulos', 'modulos.id', '=', 'alertas.modulo_id')
            ->leftJoin('sensores', 'sensores.id', '=', 'alertas.sensor_id')
            ->leftJoin('actuadores', 'actuadores.id', '=', 'alertas.actuador_id')
            ->select([
                'alertas.id',
                'alertas.modulo_id',
                'alertas.sensor_id',
                'alertas.actuador_id',
                'alertas.severidad',
                'alertas.mensaje',
                'alertas.estado',
                'alertas.created_at',
                'alertas.updated_at',
                'alertas.cerrada_en',
                'sensores.codigo as sensor_codigo',
                'sensores.nombre as sensor_nombre',
                'actuadores.codigo as actuador_codigo',
                'actuadores.nombre as actuador_nombre',
                'modulos.codigo as modulo_codigo',
            ])
            ->orderByDesc('alertas.updated_at')
            ->limit($limite);

        if ($moduloId) {
            $query->where('alertas.modulo_id', $moduloId);
        }

        return $query->get()->map(function ($row) {
            $fecha = $row->cerrada_en ?: ($row->updated_at ?: $row->created_at);
            $origen = $row->sensor_nombre ?: ($row->actuador_nombre ?: null);
            $detalle = ($row->modulo_codigo ?: 'Sistema') . ' · ' . ucfirst((string) $row->estado);
            if ($origen) {
                $detalle .= ' · ' . $origen;
            }

            return $this->item([
                'tipo' => 'alerta',
                'titulo' => $row->mensaje ?: 'Alerta del sistema',
                'detalle' => $detalle,
                'fecha' => $fecha,
                'estado' => strtoupper((string) $row->estado),
                'severidad' => $row->severidad ?: 'advertencia',
                'icono' => $row->severidad === 'critico' ? 'ri-alarm-warning-line' : 'ri-notification-3-line',
                'modulo_id' => $row->modulo_id,
                'modulo_codigo' => $row->modulo_codigo,
                'entidad_id' => $row->id,
                'url_tipo' => 'alerta',
            ]);
        });
    }

    private function auditoria(?int $moduloId, int $limite): Collection
    {
        if (!Schema::hasTable('auditoria_eventos')) {
            return collect();
        }

        $query = DB::table('auditoria_eventos')
            ->select(['id', 'actor_tipo', 'actor_id', 'evento_tipo', 'entidad_tipo', 'entidad_id', 'data', 'creado_en'])
            ->orderByDesc('creado_en')
            ->limit($moduloId ? $limite * 4 : $limite);

        return $query->get()
            ->filter(function ($row) use ($moduloId) {
                if (!$moduloId) {
                    return true;
                }

                $data = $this->json($row->data);

                return ((string) $row->entidad_tipo === 'modulo' && (int) $row->entidad_id === $moduloId)
                    || (int) data_get($data, 'modulo_id') === $moduloId;
            })
            ->map(function ($row) {
            $data = $this->json($row->data);
            $titulo = $this->eventoLegible($row->evento_tipo);
            $detalle = data_get($data, 'mensaje')
                ?: data_get($data, 'detalle')
                ?: trim(($row->actor_tipo ?: 'sistema') . ' · ' . ($row->entidad_tipo ?: 'evento'));

            return $this->item([
                'tipo' => 'auditoria',
                'titulo' => $titulo,
                'detalle' => $detalle,
                'fecha' => $row->creado_en,
                'estado' => 'LOG',
                'severidad' => 'info',
                'icono' => 'ri-history-line',
                'modulo_id' => data_get($data, 'modulo_id'),
                'modulo_codigo' => data_get($data, 'modulo_codigo'),
                'entidad_id' => $row->id,
                'url_tipo' => 'auditoria',
            ]);
        });
    }

    private function item(array $data): array
    {
        $fecha = $data['fecha'] ?? null;
        $carbon = $fecha ? Carbon::parse($fecha) : null;

        return array_merge([
            'tipo' => 'evento',
            'titulo' => 'Evento del sistema',
            'detalle' => '',
            'estado' => '',
            'severidad' => 'info',
            'icono' => 'ri-pulse-line',
            'modulo_id' => null,
            'modulo_codigo' => null,
            'entidad_id' => null,
            'url_tipo' => null,
        ], $data, [
            'fecha' => $carbon,
            'fecha_ts' => $carbon?->timestamp ?? 0,
        ]);
    }

    private function json(mixed $valor): array
    {
        if (is_array($valor)) {
            return $valor;
        }

        if (!$valor) {
            return [];
        }

        $decoded = json_decode((string) $valor, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function origenLegible(?string $origen): string
    {
        return match ($origen) {
            'manual' => 'control manual',
            'auto' => 'automatización',
            'programacion' => 'programación',
            'regla' => 'regla automática',
            default => 'sistema',
        };
    }

    private function estadoComandoLegible(?string $estado): string
    {
        return match ($estado) {
            'pendiente' => 'pendiente de entrega',
            'enviado' => 'enviado al ESP32',
            'confirmado' => 'confirmado por ACK',
            'fallido' => 'fallido sin confirmación',
            'expirado' => 'expirado',
            default => 'sin estado',
        };
    }

    private function eventoLegible(?string $evento): string
    {
        $evento = trim((string) $evento);

        if ($evento === '') {
            return 'Evento de auditoría';
        }

        return ucfirst(str_replace(['.', '_', '-'], ' ', $evento));
    }

    private function motivoLegible(array $motivo): ?string
    {
        if (!$motivo) {
            return null;
        }

        $partes = [];

        if ($sensor = data_get($motivo, 'sensor')) {
            $partes[] = 'sensor ' . $sensor;
        }

        if (($valor = data_get($motivo, 'valor')) !== null) {
            $partes[] = 'valor ' . $valor;
        }

        if ($programacion = data_get($motivo, 'programacion_id')) {
            $partes[] = 'programación #' . $programacion;
        }

        if ($regla = data_get($motivo, 'regla_id')) {
            $partes[] = 'regla #' . $regla;
        }

        return $partes ? implode(' · ', $partes) : null;
    }

    private function iconoActuador(?string $codigo, ?string $tipo): string
    {
        $texto = strtoupper(trim((string) $codigo . ' ' . (string) $tipo));

        if (str_contains($texto, 'AGUA') || str_contains($texto, 'BOMBA') || str_contains($texto, 'VALV')) {
            return 'ri-water-flash-line';
        }

        if (str_contains($texto, 'FAN') || str_contains($texto, 'VENT')) {
            return 'ri-windy-line';
        }

        if (str_contains($texto, 'CALEF') || str_contains($texto, 'HEAT') || str_contains($texto, 'RESIST')) {
            return 'ri-fire-line';
        }

        if (str_contains($texto, 'ALIMENTO') || str_contains($texto, 'FEED') || str_contains($texto, 'CROQUETA')) {
            return 'ri-seedling-line';
        }

        return 'ri-flashlight-line';
    }
}
