<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Actuador;
use App\Models\Modulo;
use App\Models\Programacion;
use App\Services\MotorProgramaciones;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProgramacionController extends Controller
{
    public function index(Request $request): View
    {
        $programaciones = Programacion::with(['modulo', 'actuador'])
            ->when($request->filled('modulo_id'), fn ($q) => $q->where('modulo_id', $request->integer('modulo_id')))
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        $modulos = Modulo::orderBy('codigo')->get();

        return view('panel.automatizacion.programaciones.index', compact('programaciones', 'modulos'));
    }

    public function create(): View
    {
        $programacion = new Programacion([
            'activo' => true,
            'duracion_seg' => 10,
            'prioridad' => 50,
            'dias' => ['lu', 'ma', 'mi', 'ju', 'vi', 'sa', 'do'],
            'estado_deseado' => ['on' => true],
        ]);
        return $this->formView('panel.automatizacion.programaciones.create', $programacion);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        $data = $this->normalizarProgramacion($request, $data);

        Programacion::create($data);

        return redirect()->route('panel.automatizacion.programaciones.index')->with('success', 'Programación registrada correctamente.');
    }

    public function show(Programacion $programacion): View
    {
        $programacion->load(['modulo', 'actuador']);
        $ejecuciones = $programacion->ejecuciones()->latest('inicio_en')->paginate(15);
        return view('panel.automatizacion.programaciones.show', compact('programacion', 'ejecuciones'));
    }

    public function edit(Programacion $programacion): View
    {
        return $this->formView('panel.automatizacion.programaciones.edit', $programacion);
    }

    public function update(Request $request, Programacion $programacion): RedirectResponse
    {
        $data = $this->validateData($request);
        $data = $this->normalizarProgramacion($request, $data);

        $programacion->update($data);

        return redirect()->route('panel.automatizacion.programaciones.index')->with('success', 'Programación actualizada correctamente.');
    }

    public function evaluarAhora(MotorProgramaciones $motor): RedirectResponse
    {
        $resultado = $motor->evaluarTodos();

        $mensaje = sprintf(
            'Programaciones evaluadas: %d. Inicios creados: %d. Apagados creados: %d. Omitidas: %d.',
            (int) ($resultado['programaciones_evaluadas'] ?? 0),
            (int) ($resultado['inicios_creados'] ?? 0),
            (int) ($resultado['finalizaciones_creadas'] ?? 0),
            (int) ($resultado['omitidas'] ?? 0)
        );

        return redirect()
            ->route('panel.automatizacion.programaciones.index')
            ->with(($resultado['ok'] ?? false) ? 'success' : 'error', $mensaje);
    }

    public function destroy(Programacion $programacion): RedirectResponse
    {
        abort_unless((auth()->user()->rol ?? null) === 'admin', 403);
        $programacion->delete();
        return redirect()->route('panel.automatizacion.programaciones.index')->with('success', 'Programación eliminada correctamente.');
    }

    private function formView(string $view, Programacion $programacion): View
    {
        $modulos = Modulo::orderBy('codigo')->get();
        $actuadores = Actuador::with('modulo')->orderBy('codigo')->get();
        $dias = ['lu' => 'Lun', 'ma' => 'Mar', 'mi' => 'Mié', 'ju' => 'Jue', 'vi' => 'Vie', 'sa' => 'Sáb', 'do' => 'Dom'];

        return view($view, compact('programacion', 'modulos', 'actuadores', 'dias'));
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'modulo_id' => ['required', 'exists:modulos,id'],
            'actuador_id' => [
                'required',
                Rule::exists('actuadores', 'id')
                    ->where(fn ($q) => $q->where('modulo_id', $request->input('modulo_id'))),
            ],
            'nombre' => ['required', 'string', 'max:160'],
            'tipo_programacion' => ['nullable', Rule::in(['alimentacion', 'agua_nivel', 'generica'])],
            'dias' => ['required', 'array', 'min:1'],
            'dias.*' => ['required', 'in:lu,ma,mi,ju,vi,sa,do,lun,mar,mie,jue,vie,sab,dom'],
            'hora_inicio' => ['required', 'date_format:H:i'],
            'duracion_seg' => ['nullable', 'integer', 'min:1', 'max:3600'],
            'nivel_objetivo' => ['nullable', 'integer', Rule::in([25, 50, 75, 100])],
            'timeout_seg' => ['nullable', 'integer', 'min:5', 'max:300'],
            'estado_deseado_json' => ['nullable', 'json'],
            'prioridad' => ['required', 'integer'],
        ]);
    }


    /**
     * Traduce el formulario amigable a la estructura JSON que entiende el ESP32.
     * No se guarda tipo_programacion como columna; queda codificado en estado_deseado.
     */
    private function normalizarProgramacion(Request $request, array $data): array
    {
        $tipo = $request->input('tipo_programacion', 'generica');

        $data['activo'] = $request->boolean('activo');
        $data['dias'] = $request->input('dias', []);

        if ($tipo === 'alimentacion') {
            $duracion = max(1, min(300, (int) ($request->input('duracion_seg') ?: 20)));
            $data['duracion_seg'] = $duracion;
            $data['estado_deseado'] = [
                'on' => true,
                'accion' => 'pulso',
                'duracion_seg' => $duracion,
            ];
        } elseif ($tipo === 'agua_nivel') {
            $nivel = (int) ($request->input('nivel_objetivo') ?: 50);
            $timeout = max(5, min(300, (int) ($request->input('timeout_seg') ?: $request->input('duracion_seg') ?: 90)));
            $data['duracion_seg'] = $timeout;
            $data['estado_deseado'] = [
                'on' => true,
                'accion' => 'llenar_hasta',
                'nivel_objetivo' => $nivel,
                'timeout_seg' => $timeout,
            ];
        } else {
            $data['duracion_seg'] = max(1, (int) ($request->input('duracion_seg') ?: 10));
            $data['estado_deseado'] = $this->jsonOrNull($request, 'estado_deseado_json') ?? ['on' => true];
        }

        unset($data['tipo_programacion'], $data['nivel_objetivo'], $data['timeout_seg'], $data['estado_deseado_json']);

        return $data;
    }

    private function jsonOrNull(Request $request, string $field): ?array
    {
        return $request->filled($field) ? json_decode($request->input($field), true) : null;
    }
}
