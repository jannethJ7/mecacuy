<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Modulo;
use App\Models\Sensor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SensorController extends Controller
{
    public function index(Request $request): View
    {
        $sensores = Sensor::with('modulo')
            ->when($request->filled('modulo_id'), fn ($q) => $q->where('modulo_id', $request->integer('modulo_id')))
            ->when($request->filled('tipo'), fn ($q) => $q->where('tipo', $request->string('tipo')))
            ->when($request->filled('buscar'), function ($query) use ($request) {
                $buscar = $request->string('buscar');
                $query->where(function ($q) use ($buscar) {
                    $q->where('codigo', 'like', "%{$buscar}%")
                      ->orWhere('nombre', 'like', "%{$buscar}%")
                      ->orWhere('tipo', 'like', "%{$buscar}%");
                });
            })
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        $modulos = Modulo::orderBy('codigo')->get();

        return view('panel.sensores.index', compact('sensores', 'modulos'));
    }

    public function create(): View
    {
        $sensor = new Sensor(['activo' => true]);
        $modulos = Modulo::orderBy('codigo')->get();
        return view('panel.sensores.create', compact('sensor', 'modulos'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        $data['activo'] = $request->boolean('activo');
        $data['meta'] = $this->jsonOrNull($request, 'meta_json');

        Sensor::create($data);

        return redirect()->route('panel.sensores.index')->with('success', 'Sensor registrado correctamente.');
    }

    public function show(Sensor $sensor): View
    {
        $sensor->load('modulo');
        $lecturas = $sensor->lecturas()->latest('medido_en')->paginate(15);
        return view('panel.sensores.show', compact('sensor', 'lecturas'));
    }

    public function edit(Sensor $sensor): View
    {
        $modulos = Modulo::orderBy('codigo')->get();
        return view('panel.sensores.edit', compact('sensor', 'modulos'));
    }

    public function update(Request $request, Sensor $sensor): RedirectResponse
    {
        $data = $this->validateData($request, $sensor->id);
        $data['activo'] = $request->boolean('activo');
        $data['meta'] = $this->jsonOrNull($request, 'meta_json');

        $sensor->update($data);

        return redirect()->route('panel.sensores.index')->with('success', 'Sensor actualizado correctamente.');
    }

    public function destroy(Sensor $sensor): RedirectResponse
    {
        abort_unless((auth()->user()->rol ?? null) === 'admin', 403);
        $sensor->delete();
        return redirect()->route('panel.sensores.index')->with('success', 'Sensor eliminado correctamente.');
    }

    private function validateData(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'modulo_id' => ['required', 'exists:modulos,id'],
            'codigo' => ['required', 'string', 'max:40'],
            'nombre' => ['required', 'string', 'max:120'],
            'tipo' => ['required', 'string', 'max:40'],
            'unidad' => ['nullable', 'string', 'max:20'],
            'gpio_pin' => ['nullable', 'integer', 'min:0', 'max:39'],
            'meta_json' => ['nullable', 'json'],
        ]);
    }

    private function jsonOrNull(Request $request, string $field): ?array
    {
        return $request->filled($field) ? json_decode($request->input($field), true) : null;
    }
}
