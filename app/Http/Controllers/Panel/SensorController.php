<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Services\SupervisionCalefaccion;
use App\Models\Actuador;
use App\Services\NivelAgua;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use App\Models\Modulo;
use App\Models\Sensor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SensorController extends Controller
{
    public function index(Request $request): View
    {
        $sensores = Sensor::with('modulo')->whereNull('meta->nivel_agua_reemplazado_por')
            ->when(in_array($request->input('estado'), ['activo', 'inactivo'], true), fn ($q) => $q->where('activo', $request->input('estado') === 'activo'))
            ->when($request->filled('modulo_id'), fn ($q) => $q->where('modulo_id', $request->integer('modulo_id')))
            ->when($request->filled('tipo'), fn ($q) => $q->where('tipo', $request->string('tipo')))
            ->when($request->filled('buscar'), function ($query) use ($request) {
                $buscar = $request->string('buscar');
                $query->where(function ($q) use ($buscar) {
                    $q->where('codigo', 'like', "%{$buscar}%")
                      ->orWhere('nombre', 'like', "%{$buscar}%")
                      ->orWhere('tipo', 'like', "%{$buscar}%")
                      ->orWhereHas('modulo', fn ($modulo) => $modulo->where('codigo', 'like', "%{$buscar}%")->orWhere('nombre', 'like', "%{$buscar}%"));
                });
            })
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $modulos = Modulo::orderBy('codigo')->get();

        return view('panel.sensores.index', compact('sensores', 'modulos'));
    }

    public function create(): View
    {
        $sensor = new Sensor(['activo' => true]);
        $modulos = Modulo::orderBy('codigo')->get();
        return view('panel.sensores.create', ['sensor' => $sensor, 'modulos' => $modulos, 'actuadores' => Actuador::with('modulo')->orderBy('codigo')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        $data['activo'] = $request->boolean('activo');
        $data['meta'] = $this->metaConfigurado($request);

        DB::transaction(function () use ($data) {
            $sensor = Sensor::create($data);
            if (NivelAgua::esSensor($sensor)) app(NivelAgua::class)->consolidar($sensor);
        });

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
        return view('panel.sensores.edit', ['sensor' => $sensor, 'modulos' => $modulos, 'actuadores' => Actuador::with('modulo')->orderBy('codigo')->get()]);
    }

    public function update(Request $request, Sensor $sensor): RedirectResponse
    {
        $data = $this->validateData($request, $sensor->id);
        $data['activo'] = $request->boolean('activo');
        $data['meta'] = $this->metaConfigurado($request);

        DB::transaction(function () use ($sensor, $data) {
            if ($data['tipo'] === NivelAgua::TIPO && !NivelAgua::esSensor($sensor)) {
                $data['valor_actual'] = null;
                $data['valor_actual_en'] = null;
            }
            $sensor->update($data);
            if (NivelAgua::esSensor($sensor)) app(NivelAgua::class)->consolidar($sensor);
        });

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
        if ($request->input('tipo') === NivelAgua::TIPO) {
            $request->merge(['codigo' => NivelAgua::CODIGO, 'gpio_pin' => null, 'unidad' => '%']);
            $existente = $request->route('sensor');
            if ($existente && NivelAgua::nivelIndividual($existente->codigo) !== null) {
                throw ValidationException::withMessages(['tipo' => 'Registra un nuevo sensor de nivel de agua para conservar el historial de esta entrada.']);
            }
        }
        return $request->validate([
            'modulo_id' => ['required', 'exists:modulos,id'],
            'codigo' => ['required', 'string', 'max:40', Rule::unique('sensores', 'codigo')->where(fn ($q) => $q->where('modulo_id', $request->input('modulo_id')))->ignore($ignoreId)],
            'nombre' => ['required', 'string', 'max:120'],
            'tipo' => ['required', 'string', 'max:40'],
            'unidad' => ['nullable', 'string', 'max:20'],
            'gpio_pin' => ['nullable', 'integer', 'min:0', 'max:39'],
            'meta_json' => ['nullable', 'json'],
            'calefaccion_actuador' => ['required_if:tipo,verificacion_calefaccion', 'nullable', 'string', 'max:40'],
            'calefaccion_calibrado' => ['nullable', 'boolean'],
            'calefaccion_cero_mv' => ['required_if:tipo,verificacion_calefaccion', 'nullable', 'numeric', 'min:100', 'max:3000'],
            'calefaccion_umbral_encendido_mv' => ['required_if:tipo,verificacion_calefaccion', 'nullable', 'numeric', 'min:1', 'max:1000'],
            'calefaccion_umbral_apagado_mv' => ['required_if:tipo,verificacion_calefaccion', 'nullable', 'numeric', 'min:0', 'max:999'],
            'calefaccion_cero_min_mv' => ['required_if:tipo,verificacion_calefaccion', 'nullable', 'numeric', 'min:100', 'max:2900'],
            'calefaccion_cero_max_mv' => ['required_if:tipo,verificacion_calefaccion', 'nullable', 'numeric', 'min:101', 'max:3000'],
            'calefaccion_espera_seg' => ['required_if:tipo,verificacion_calefaccion', 'nullable', 'integer', 'min:2', 'max:30'],
            'calefaccion_vigencia_seg' => ['required_if:tipo,verificacion_calefaccion', 'nullable', 'integer', 'min:15', 'max:120'],
        ]);
    }

    private function metaConfigurado(Request $request): ?array
    {
        if ($request->input('tipo') === NivelAgua::TIPO) {
            app(NivelAgua::class)->validarConjunto((int) $request->input('modulo_id'), $request->route('sensor')?->id);
            return NivelAgua::metadatos();
        }
        if ($request->boolean('activo')) {
            NivelAgua::validarPinIndividual((int) $request->input('modulo_id'), $request->filled('gpio_pin') ? (int) $request->input('gpio_pin') : null);
        }
        if ($request->input('tipo') !== SupervisionCalefaccion::TIPO) {
            return $request->filled('meta_json') ? $this->jsonOrNull($request, 'meta_json') : $request->route('sensor')?->meta;
        }
        $moduloId = (int) $request->input('modulo_id');
        $sensorId = $request->route('sensor')?->id;
        $gpio = $request->filled('gpio_pin') ? (int) $request->input('gpio_pin') : null;
        $errores = [];
        if (!in_array($gpio, [32, 33, 34, 35, 36, 39], true)) {
            $errores['gpio_pin'] = 'ACS712 requiere una entrada ADC1 libre del ESP32 clásico (32, 33, 34, 35, 36 o 39).';
        }
        if (Sensor::where('modulo_id', $moduloId)->where('gpio_pin', $gpio)
            ->when($sensorId, fn ($q) => $q->where('id', '!=', $sensorId))->exists()
            || Actuador::where('modulo_id', $moduloId)->where('gpio_pin', $gpio)->exists()) {
            $errores['gpio_pin'] = 'Este GPIO ya está asignado en el módulo.';
        }
        $codigo = $request->input('calefaccion_actuador');
        if (!Actuador::where('modulo_id', $moduloId)->where('codigo', $codigo)->where('activo', true)->exists()) {
            $errores['calefaccion_actuador'] = 'Selecciona un actuador activo del mismo módulo.';
        }
        if (Sensor::where('modulo_id', $moduloId)->where('tipo', SupervisionCalefaccion::TIPO)
            ->when($sensorId, fn ($q) => $q->where('id', '!=', $sensorId))->get()
            ->contains(fn ($s) => data_get($s->meta, 'actuador_codigo') === $codigo)) {
            $errores['calefaccion_actuador'] = 'El actuador ya tiene un sensor de verificación.';
        }
        if ((float) $request->input('calefaccion_umbral_encendido_mv') <= (float) $request->input('calefaccion_umbral_apagado_mv')) {
            $errores['calefaccion_umbral_encendido_mv'] = 'El umbral de encendido debe superar al de apagado.';
        }
        if ((float) $request->input('calefaccion_cero_max_mv') <= (float) $request->input('calefaccion_cero_min_mv')) {
            $errores['calefaccion_cero_max_mv'] = 'El límite superior debe superar al inferior.';
        }
        if ((float) $request->input('calefaccion_cero_mv') < (float) $request->input('calefaccion_cero_min_mv')
            || (float) $request->input('calefaccion_cero_mv') > (float) $request->input('calefaccion_cero_max_mv')) {
            $errores['calefaccion_cero_mv'] = 'La referencia de cero debe estar dentro del intervalo válido.';
        }
        if ($errores) {
            throw ValidationException::withMessages($errores);
        }
        $meta = SupervisionCalefaccion::predeterminados($codigo);
        $meta['calibrado'] = $request->boolean('calefaccion_calibrado');
        foreach (['umbral_encendido_mv', 'umbral_apagado_mv', 'cero_mv', 'cero_min_mv', 'cero_max_mv', 'espera_seg', 'vigencia_seg'] as $campo) {
            $meta[$campo] = (float) $request->input('calefaccion_'.$campo);
        }
        return $meta;
    }

    private function jsonOrNull(Request $request, string $field): ?array
    {
        return $request->filled($field) ? json_decode($request->input($field), true) : null;
    }
}
