<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Actuador;
use App\Models\Modulo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ActuadorController extends Controller
{
    public function index(Request $request): View
    {
        $actuadores = Actuador::with('modulo')
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

        $modoRaw = DB::table('config_sistema')->where('clave', 'modo_global')->value('valor');
        $modo = $modoRaw ? (json_decode($modoRaw, true) ?? 'manual') : 'manual';

        return view('panel.actuadores.index', compact('actuadores', 'modulos', 'modo'));
    }

    public function create(): View
    {
        $actuador = new Actuador(['activo' => true, 'invertido' => false]);
        $modulos = Modulo::orderBy('codigo')->get();
        return view('panel.actuadores.create', compact('actuador', 'modulos'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        $data['activo'] = $request->boolean('activo');
        $data['invertido'] = $request->boolean('invertido');
        $data['estado_deseado'] = $this->jsonOrNull($request, 'estado_deseado_json');
        $data['estado_reportado'] = $this->jsonOrNull($request, 'estado_reportado_json');
        $data['meta'] = $this->jsonOrNull($request, 'meta_json');

        Actuador::create($data);

        return redirect()->route('panel.actuadores.index')->with('success', 'Actuador registrado correctamente.');
    }

    public function show(Actuador $actuador): View
    {
        $actuador->load('modulo');
        $actuaciones = $actuador->actuaciones()->latest('ejecutado_en')->paginate(15);
        return view('panel.actuadores.show', compact('actuador', 'actuaciones'));
    }

    public function edit(Actuador $actuador): View
    {
        $modulos = Modulo::orderBy('codigo')->get();
        return view('panel.actuadores.edit', compact('actuador', 'modulos'));
    }

    public function update(Request $request, Actuador $actuador): RedirectResponse
    {
        $data = $this->validateData($request, $actuador->id);
        $data['activo'] = $request->boolean('activo');
        $data['invertido'] = $request->boolean('invertido');
        $data['estado_deseado'] = $this->jsonOrNull($request, 'estado_deseado_json');
        $data['estado_reportado'] = $this->jsonOrNull($request, 'estado_reportado_json');
        $data['meta'] = $this->jsonOrNull($request, 'meta_json');

        $actuador->update($data);

        return redirect()->route('panel.actuadores.index')->with('success', 'Actuador actualizado correctamente.');
    }

    public function destroy(Actuador $actuador): RedirectResponse
    {
        abort_unless((auth()->user()->rol ?? null) === 'admin', 403);
        $actuador->delete();
        return redirect()->route('panel.actuadores.index')->with('success', 'Actuador eliminado correctamente.');
    }

    private function validateData(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'modulo_id' => ['required', 'exists:modulos,id'],
            'codigo' => [
                'required',
                'string',
                'max:40',
                Rule::unique('actuadores', 'codigo')
                    ->where(fn ($q) => $q->where('modulo_id', $request->input('modulo_id')))
                    ->ignore($ignoreId),
            ],
            'nombre' => ['required', 'string', 'max:120'],
            'tipo' => ['required', 'string', 'max:40'],
            'gpio_pin' => ['nullable', 'integer'],
            'estado_deseado_json' => ['nullable', 'json'],
            'estado_reportado_json' => ['nullable', 'json'],
            'meta_json' => ['nullable', 'json'],
        ]);
    }

    private function jsonOrNull(Request $request, string $field): ?array
    {
        return $request->filled($field) ? json_decode($request->input($field), true) : null;
    }
    public function manual(Request $request, Actuador $actuador): JsonResponse
    {
        abort_unless(in_array(auth()->user()->rol ?? null, ['admin', 'operador']), 403);

        $data = $request->validate([
            'on' => ['required', 'boolean'],
            'accion' => ['nullable', 'string', Rule::in(['set_estado', 'pulso', 'llenar_hasta'])],
            'duracion_seg' => ['nullable', 'integer', 'min:1', 'max:300'],
            'nivel_objetivo' => ['nullable', 'integer', Rule::in([25, 50, 75, 100])],
            'timeout_seg' => ['nullable', 'integer', 'min:5', 'max:300'],
        ]);

        $modo = DB::table('config_sistema')
            ->where('clave', 'modo_global')
            ->value('valor');

        $modo = $modo ? json_decode($modo, true) : 'manual';

        if ($modo !== 'manual') {
            return response()->json([
                'ok' => false,
                'error' => 'El sistema está en modo automático.',
            ], 423);
        }

        if (!$actuador->activo) {
            return response()->json([
                'ok' => false,
                'error' => 'El actuador está inactivo.',
            ], 422);
        }

        $accion = $data['accion'] ?? 'set_estado';
        $estadoAnterior = $actuador->estado_deseado;
        $estadoNuevo = ['on' => (bool) $data['on']];

        $payloadExtra = [
            'origen' => 'manual',
            'usuario_id' => auth()->id(),
            'accion' => $accion,
        ];

        $crearApagadoSeguro = false;
        $segundosApagado = null;

        if ($accion === 'pulso') {
            $duracion = (int) ($data['duracion_seg'] ?? 10);
            $payloadExtra['duracion_seg'] = $duracion;
            $payloadExtra['seguridad'] = 'apagado_local_y_comando_final';
            $crearApagadoSeguro = (bool) $estadoNuevo['on'];
            $segundosApagado = $duracion;
        }

        if ($accion === 'llenar_hasta') {
            $nivel = (int) ($data['nivel_objetivo'] ?? 50);
            $timeout = (int) ($data['timeout_seg'] ?? 60);
            $payloadExtra['nivel_objetivo'] = $nivel;
            $payloadExtra['timeout_seg'] = $timeout;
            $payloadExtra['seguridad'] = 'cierra_por_nivel_o_timeout_y_comando_final';
            $crearApagadoSeguro = (bool) $estadoNuevo['on'];
            $segundosApagado = $timeout;
        }

        DB::transaction(function () use ($actuador, $estadoAnterior, $estadoNuevo, $payloadExtra, $crearApagadoSeguro, $segundosApagado) {
            $actuador->update([
                'estado_deseado' => $estadoNuevo,
                'cambiado_en' => now(),
            ]);

            $this->crearComandoManual($actuador, $estadoNuevo, $payloadExtra, now());

            if ($crearApagadoSeguro && $segundosApagado !== null) {
                $this->crearComandoManual(
                    $actuador,
                    ['on' => false],
                    array_merge($payloadExtra, [
                        'accion' => 'apagado_seguro',
                        'causa' => 'finalizacion_manual_temporizada',
                        'retardo_seg' => $segundosApagado,
                    ]),
                    now()->addSeconds($segundosApagado + 1)
                );
            }

            DB::table('actuaciones')->insert([
                'modulo_id' => $actuador->modulo_id,
                'actuador_id' => $actuador->id,
                'origen' => 'manual',
                'estado_anterior' => json_encode($estadoAnterior),
                'estado_nuevo' => json_encode($estadoNuevo),
                'motivo' => json_encode([
                    'usuario_id' => auth()->id(),
                    'fuente' => 'panel_manual',
                ] + $payloadExtra),
                'ejecutado_en' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return response()->json([
            'ok' => true,
            'estado' => $estadoNuevo,
            'accion' => $accion,
            'mensaje' => $this->mensajeManual($accion, $data),
        ]);
    }

    private function crearComandoManual(Actuador $actuador, array $estado, array $extra, $ejecutarEn = null): void
    {
        DB::table('comandos_iot')->insert([
            'modulo_id' => $actuador->modulo_id,
            'actuador_id' => $actuador->id,
            'tipo' => 'set_estado',
            'payload' => json_encode([
                'actuador' => $actuador->codigo,
                'estado' => $estado,
            ] + $extra),
            'estado' => 'pendiente',
            'nonce' => (string) Str::uuid(),
            'intentos' => 0,
            'ejecutar_en' => $ejecutarEn,
            'expira_en' => ($ejecutarEn ?: now())->copy()->addMinutes(5),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function mensajeManual(string $accion, array $data): string
    {
        return match ($accion) {
            'pulso' => 'Alimentación activada por '.((int) ($data['duracion_seg'] ?? 10)).' segundos.',
            'llenar_hasta' => 'Llenado de agua iniciado hasta '.((int) ($data['nivel_objetivo'] ?? 50)).'%.',
            default => 'Comando manual enviado.',
        };
    }

}
