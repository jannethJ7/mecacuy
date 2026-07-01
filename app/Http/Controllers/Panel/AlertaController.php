<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Alerta;
use App\Models\Modulo;
use App\Services\MotorAlertasAutomaticas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AlertaController extends Controller
{
    public function index(Request $request): View
    {
        $perPage = in_array((int) $request->input('per_page', 10), [10, 20, 50, 100], true)
            ? (int) $request->input('per_page', 10)
            : 10;

        $q = trim((string) $request->input('q', ''));

        $alertas = Alerta::with(['modulo', 'sensor', 'actuador', 'reconocidaPor'])
            ->when($request->filled('modulo_id'), fn ($query) => $query->where('modulo_id', $request->integer('modulo_id')))
            ->when($request->filled('estado'), fn ($query) => $query->where('estado', $request->string('estado')))
            ->when($request->filled('severidad'), fn ($query) => $query->where('severidad', $request->string('severidad')))
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('mensaje', 'like', "%{$q}%")
                        ->orWhere('severidad', 'like', "%{$q}%")
                        ->orWhere('estado', 'like', "%{$q}%")
                        ->orWhereHas('modulo', fn ($modulo) => $modulo->where('codigo', 'like', "%{$q}%")->orWhere('nombre', 'like', "%{$q}%"))
                        ->orWhereHas('sensor', fn ($sensor) => $sensor->where('codigo', 'like', "%{$q}%")->orWhere('nombre', 'like', "%{$q}%"))
                        ->orWhereHas('actuador', fn ($actuador) => $actuador->where('codigo', 'like', "%{$q}%")->orWhere('nombre', 'like', "%{$q}%"));
                });
            })
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        $modulos = Modulo::orderBy('codigo')->get();

        return view('panel.alertas.index', compact('alertas', 'modulos', 'perPage', 'q'));
    }

    public function show(Alerta $alerta): View
    {
        $alerta->load(['modulo', 'sensor', 'actuador', 'reglaAlerta', 'reconocidaPor']);
        return view('panel.alertas.show', compact('alerta'));
    }

    public function evaluarAhora(MotorAlertasAutomaticas $motor): RedirectResponse
    {
        abort_unless(in_array(auth()->user()->rol ?? null, ['admin', 'operador'], true), 403);

        $resultado = $motor->evaluarTodos();

        $mensaje = sprintf(
            'Evaluación completada: %d reglas revisadas, %d alerta(s) nueva(s), %d duplicada(s), %d en enfriamiento y %d cerrada(s) automáticamente.',
            (int) ($resultado['evaluadas'] ?? 0),
            (int) ($resultado['abiertas'] ?? 0),
            (int) ($resultado['duplicadas'] ?? 0),
            (int) ($resultado['en_enfriamiento'] ?? 0),
            (int) ($resultado['cerradas'] ?? 0),
        );

        if (!($resultado['ok'] ?? false)) {
            return back()->with('error', $mensaje . ' Algunos módulos no pudieron evaluarse.');
        }

        return back()->with('success', $mensaje);
    }

    public function reconocer(Alerta $alerta): RedirectResponse
    {
        $alerta->update([
            'estado' => 'reconocida',
            'reconocida_por_user_id' => auth()->id(),
            'reconocida_en' => now(),
        ]);

        return back()->with('success', 'Alerta reconocida correctamente.');
    }

    public function cerrar(Alerta $alerta): RedirectResponse
    {
        $alerta->update([
            'estado' => 'cerrada',
            'cerrada_en' => now(),
        ]);

        return back()->with('success', 'Alerta cerrada correctamente.');
    }

    public function destroy(Alerta $alerta): RedirectResponse
    {
        abort_unless((auth()->user()->rol ?? null) === 'admin', 403);
        $alerta->delete();
        return redirect()->route('panel.alertas.index')->with('success', 'Alerta eliminada correctamente.');
    }
}
