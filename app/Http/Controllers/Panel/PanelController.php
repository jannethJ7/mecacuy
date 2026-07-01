<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Alerta;
use App\Services\ActividadRecienteService;
use App\Services\SeriesLecturasService;
use Illuminate\Support\Facades\DB;

class PanelController extends Controller
{
    public function dashboard(SeriesLecturasService $seriesLecturas, ActividadRecienteService $actividadReciente)
    {
        $staleMin = (int) $this->getConfig('stale_min', 10);

        $modulosTotal = DB::table('modulos')->count();
        $onlineDesde = now()->subMinutes($staleMin);

        $modulosOnline = DB::table('modulos')
            ->whereNotNull('ultimo_contacto')
            ->where('ultimo_contacto', '>=', $onlineDesde)
            ->count();

        $alertasAbiertas = DB::table('alertas')->where('estado', 'abierta')->count();
        $alertasCriticas = DB::table('alertas')->where('estado', 'abierta')->where('severidad', 'critico')->count();

        $modulos = DB::table('modulos')
            ->orderBy('id')
            ->limit(10)
            ->get(['id', 'codigo', 'nombre', 'uid', 'ultimo_contacto']);

        $alertas = Alerta::query()
            ->with('modulo')
            ->where('estado', 'abierta')
            ->latest('created_at')
            ->limit(5)
            ->get();

        return view('panel.dashboard', [
            'kpis' => [
                'modulos_total' => $modulosTotal,
                'modulos_online' => $modulosOnline,
                'alertas_abiertas' => $alertasAbiertas,
                'alertas_criticas' => $alertasCriticas,
            ],
            'modulos' => $modulos,
            'alertas' => $alertas,
            'seriesDashboard' => $seriesLecturas->paraDashboard(30),
            'actividadReciente' => $actividadReciente->paraDashboard(10),
        ]);
    }

    private function getConfig(string $clave, $default = null)
    {
        $row = DB::table('config_sistema')->where('clave', $clave)->first();
        if (!$row) return $default;

        $val = json_decode($row->valor, true);
        return $val ?? $default;
    }
}