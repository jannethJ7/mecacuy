<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Lectura;
use App\Models\Modulo;
use App\Models\Sensor;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LecturaController extends Controller
{
    public function index(Request $request): View
    {
        $perPage = in_array((int) $request->input('per_page', 20), [10, 20, 50, 100], true)
            ? (int) $request->input('per_page', 20)
            : 20;

        $from = $request->input('from', $request->input('desde'));
        $to = $request->input('to', $request->input('hasta'));
        $q = trim((string) $request->input('q', ''));

        $lecturas = Lectura::with(['sensor.modulo'])
            ->when($request->filled('sensor_id'), fn ($query) => $query->where('sensor_id', $request->integer('sensor_id')))
            ->when($request->filled('modulo_id'), function ($query) use ($request) {
                $query->whereHas('sensor', fn ($sensor) => $sensor->where('modulo_id', $request->integer('modulo_id')));
            })
            ->when($from, function ($query) use ($from) {
                $query->where('medido_en', '>=', Carbon::parse($from));
            })
            ->when($to, function ($query) use ($to) {
                $query->where('medido_en', '<=', Carbon::parse($to));
            })
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('valor', 'like', "%{$q}%")
                        ->orWhere('calidad', 'like', "%{$q}%")
                        ->orWhereHas('sensor', function ($sensor) use ($q) {
                            $sensor->where('nombre', 'like', "%{$q}%")
                                ->orWhere('codigo', 'like', "%{$q}%")
                                ->orWhere('tipo', 'like', "%{$q}%");
                        });
                });
            })
            ->latest('medido_en')
            ->paginate($perPage)
            ->withQueryString();

        $modulos = Modulo::orderBy('codigo')->get();
        $sensores = Sensor::with('modulo')->orderBy('nombre')->orderBy('codigo')->get();

        return view('panel.lecturas.index', compact('lecturas', 'modulos', 'sensores', 'perPage', 'from', 'to', 'q'));
    }
}
