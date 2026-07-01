<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Modulo;
use App\Services\ActividadRecienteService;
use App\Services\SeriesLecturasService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ModuloController extends Controller
{
    public function index(Request $request): View
    {
        $modulos = Modulo::query()
            ->withCount(['sensores', 'actuadores', 'alertas'])
            ->when($request->filled('buscar'), function ($query) use ($request) {
                $buscar = $request->string('buscar');
                $query->where(function ($q) use ($buscar) {
                    $q->where('codigo', 'like', "%{$buscar}%")
                      ->orWhere('nombre', 'like', "%{$buscar}%")
                      ->orWhere('uid', 'like', "%{$buscar}%");
                });
            })
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        return view('panel.modulos.index', compact('modulos'));
    }

    public function create(): View
    {
        $modulo = new Modulo(['habilitado' => true, 'zona_horaria' => 'America/La_Paz']);
        return view('panel.modulos.create', compact('modulo'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        $data['habilitado'] = $request->boolean('habilitado');
        $data['meta'] = $this->jsonOrNull($request, 'meta_json');

        Modulo::create($data);

        return redirect()->route('panel.modulos.index')->with('success', 'Módulo registrado correctamente.');
    }

    public function show(Modulo $modulo, ActividadRecienteService $actividadService): View
    {
        $modulo->load([
            'sensores.ultimaLectura',
            'actuadores',
        ]);

        $sensores = $modulo->sensores;
        $actuadores = $modulo->actuadores;

        $modo = $this->configValor('modo_global', 'manual');
        $staleMin = (int) $this->configValor('stale_min', 5);
        $staleMin = max($staleMin, 1);

        $ultimoContacto = collect([$modulo->ultimo_contacto])
            ->merge($sensores->map(fn ($sensor) => $sensor->valor_actual_en ?: optional($sensor->ultimaLectura)->medido_en))
            ->filter()
            ->sortDesc()
            ->first();

        $estaOnline = $modulo->habilitado
            && $ultimoContacto
            && $ultimoContacto->greaterThanOrEqualTo(now()->subMinutes($staleMin));

        $actividadReciente = $actividadService->paraModulo($modulo->id, 8);

        $seriesGraficas = app(SeriesLecturasService::class)->porSensores($sensores, 24);

        return view('panel.modulos.show', compact(
            'modulo',
            'sensores',
            'actuadores',
            'modo',
            'staleMin',
            'ultimoContacto',
            'estaOnline',
            'actividadReciente',
            'seriesGraficas'
        ));
    }

    public function edit(Modulo $modulo): View
    {
        return view('panel.modulos.edit', compact('modulo'));
    }

    public function update(Request $request, Modulo $modulo): RedirectResponse
    {
        $data = $this->validateData($request, $modulo->id);
        $data['habilitado'] = $request->boolean('habilitado');
        $data['meta'] = $this->jsonOrNull($request, 'meta_json');

        $modulo->update($data);

        return redirect()->route('panel.modulos.index')->with('success', 'Módulo actualizado correctamente.');
    }

    public function destroy(Modulo $modulo): RedirectResponse
    {
        abort_unless((auth()->user()->rol ?? null) === 'admin', 403);

        $modulo->delete();

        return redirect()->route('panel.modulos.index')->with('success', 'Módulo eliminado correctamente.');
    }


    private function configValor(string $clave, mixed $default = null): mixed
    {
        $valor = DB::table('config_sistema')->where('clave', $clave)->value('valor');

        if ($valor === null) {
            return $default;
        }

        $decoded = json_decode($valor, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $valor;
    }

    private function validateData(Request $request, ?int $ignoreId = null): array
    {
        $idRule = $ignoreId ? ',' . $ignoreId : '';

        return $request->validate([
            'codigo' => ['required', 'string', 'max:50', 'unique:modulos,codigo' . $idRule],
            'nombre' => ['nullable', 'string', 'max:120'],
            'uid' => ['required', 'string', 'max:100', 'unique:modulos,uid' . $idRule],
            'version_firmware' => ['nullable', 'string', 'max:40'],
            'ip' => ['nullable', 'string', 'max:45'],
            'rssi' => ['nullable', 'integer'],
            'zona_horaria' => ['required', 'string', 'max:60'],
            'meta_json' => ['nullable', 'json'],
        ]);
    }

    private function jsonOrNull(Request $request, string $field): ?array
    {
        return $request->filled($field) ? json_decode($request->input($field), true) : null;
    }
}
