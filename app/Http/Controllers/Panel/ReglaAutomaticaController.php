<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Actuador;
use App\Models\Modulo;
use App\Models\ReglaAutomatica;
use App\Models\Sensor;
use App\Services\MotorReglasAutomaticas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Validation\ValidationException;

class ReglaAutomaticaController extends Controller
{
    public function index(Request $request): View
    {
        $reglas = ReglaAutomatica::with(['modulo', 'sensor', 'actuador', 'estado'])
            ->when($request->filled('modulo_id'), fn ($q) => $q->where('modulo_id', $request->integer('modulo_id')))
            ->when($request->filled('estado'), fn ($q) => $q->where('activo', $request->input('estado') === 'activa'))
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        $modulos = Modulo::orderBy('codigo')->get();

        return view('panel.automatizacion.reglas.index', compact('reglas', 'modulos'));
    }

    public function create(): View
    {
        $regla = new ReglaAutomatica(['activo' => true, 'histeresis' => 0, 'retardo_seg' => 0, 'prioridad' => 100]);
        return $this->formView('panel.automatizacion.reglas.create', $regla);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        $data['activo'] = $request->boolean('activo');
        $data['payload'] = $this->jsonOrNull($request, 'payload_json');

        ReglaAutomatica::create($data);

        return redirect()->route('panel.automatizacion.reglas.index')->with('success', 'Regla automática registrada correctamente.');
    }

    public function show(ReglaAutomatica $regla): View
    {
        $regla->load(['modulo', 'sensor', 'actuador', 'estado']);
        return view('panel.automatizacion.reglas.show', compact('regla'));
    }

    public function edit(ReglaAutomatica $regla): View
    {
        return $this->formView('panel.automatizacion.reglas.edit', $regla);
    }

    public function update(Request $request, ReglaAutomatica $regla): RedirectResponse
    {
        $data = $this->validateData($request);
        $data['activo'] = $request->boolean('activo');
        $data['payload'] = $this->jsonOrNull($request, 'payload_json');

        $regla->update($data);

        return redirect()->route('panel.automatizacion.reglas.index')->with('success', 'Regla automática actualizada correctamente.');
    }

    public function evaluarAhora(Request $request, MotorReglasAutomaticas $motor): RedirectResponse
    {
        abort_unless(in_array(auth()->user()->rol ?? null, ['admin', 'operador']), 403);

        $moduloId = $request->integer('modulo_id') ?: null;
        $resultado = $moduloId
            ? $motor->evaluarModulo($moduloId)
            : $motor->evaluarTodos();

        if (!empty($resultado['omitido'])) {
            return back()->with('info', 'No se evaluaron reglas: el sistema está en modo manual. Cambia a modo automático en Ajustes del sistema.');
        }

        return back()->with('success', sprintf(
            'Motor evaluado: %d regla(s), %d comando(s) generado(s).',
            $resultado['reglas_evaluadas'] ?? 0,
            $resultado['comandos_creados'] ?? 0
        ));
    }

    public function destroy(ReglaAutomatica $regla): RedirectResponse
    {
        abort_unless((auth()->user()->rol ?? null) === 'admin', 403);
        $regla->delete();
        return redirect()->route('panel.automatizacion.reglas.index')->with('success', 'Regla eliminada correctamente.');
    }

    private function formView(string $view, ReglaAutomatica $regla): View
    {
        $modulos = Modulo::orderBy('codigo')->get();
        $sensores = Sensor::with('modulo')->orderBy('codigo')->get();
        $actuadores = Actuador::with('modulo')->orderBy('codigo')->get();

        return view($view, compact('regla', 'modulos', 'sensores', 'actuadores'));
    }

    private function validateData(Request $request): array
    {
        $data = $request->validate([
            'modulo_id' => ['required', 'exists:modulos,id'],
            'sensor_id' => ['required', 'exists:sensores,id'],
            'actuador_id' => ['required', 'exists:actuadores,id'],
            'nombre' => ['required', 'string', 'max:160'],
            'objetivo_min' => ['nullable', 'numeric', 'required_without:objetivo_max'],
            'objetivo_max' => ['nullable', 'numeric', 'required_without:objetivo_min'],
            'histeresis' => ['required', 'numeric', 'min:0'],
            'retardo_seg' => ['required', 'integer', 'min:0'],
            'payload_json' => ['nullable', 'json'],
            'prioridad' => ['required', 'integer'],
        ]);

        $sensor = Sensor::find($data['sensor_id']);
        $actuador = Actuador::find($data['actuador_id']);
        $errores = [];

        if ($sensor && (int) $sensor->modulo_id !== (int) $data['modulo_id']) {
            $errores['sensor_id'] = 'El sensor seleccionado no pertenece al módulo de la regla.';
        }

        if ($actuador && (int) $actuador->modulo_id !== (int) $data['modulo_id']) {
            $errores['actuador_id'] = 'El actuador seleccionado no pertenece al módulo de la regla.';
        }

        if (!empty($errores)) {
            throw ValidationException::withMessages($errores);
        }

        return $data;
    }

    private function jsonOrNull(Request $request, string $field): ?array
    {
        return $request->filled($field) ? json_decode($request->input($field), true) : null;
    }
}
