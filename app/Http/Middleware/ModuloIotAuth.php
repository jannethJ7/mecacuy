<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ModuloIotAuth
{
    public function handle(Request $request, Closure $next)
    {
        // Headers obligatorios
        $uid = $request->header('X-MODULO-UID');   // Ej: ESP32-MOD-001
        $key = $request->header('X-DEVICE-KEY');   // API key del módulo

        if (!$uid || !$key) {
            return response()->json([
                'ok' => false,
                'error' => 'Faltan headers: X-MODULO-UID y/o X-DEVICE-KEY',
            ], 401);
        }

        $modulo = DB::table('modulos')->where('uid', $uid)->first();
        if (!$modulo || !(bool)$modulo->habilitado) {
            return response()->json([
                'ok' => false,
                'error' => 'Módulo no encontrado o deshabilitado',
            ], 401);
        }

        $cred = DB::table('modulos_credenciales')
            ->where('modulo_id', $modulo->id)
            ->first();

        if (!$cred || $cred->revocado_en) {
            return response()->json([
                'ok' => false,
                'error' => 'Credencial inexistente o revocada',
            ], 401);
        }

        if (!Hash::check($key, $cred->api_key_hash)) {
            return response()->json([
                'ok' => false,
                'error' => 'API key inválida',
            ], 401);
        }

        // Telemetría opcional
        $ip   = $request->ip();
        $rssi = $request->header('X-RSSI');
        $fw   = $request->header('X-FW');

        DB::table('modulos')->where('id', $modulo->id)->update([
            'ultimo_contacto'   => now(),
            'ip'                => $ip,
            'rssi'              => is_numeric($rssi) ? (int)$rssi : $modulo->rssi,
            'version_firmware'  => $fw ?: $modulo->version_firmware,
            'updated_at'        => now(),
        ]);

        DB::table('modulos_credenciales')->where('id', $cred->id)->update([
            'ultimo_uso_en' => now(),
            'updated_at'    => now(),
        ]);

        // Guardamos el módulo en el request para usarlo en el controlador
        $request->attributes->set('iot_modulo', $modulo);

        return $next($request);
    }
}