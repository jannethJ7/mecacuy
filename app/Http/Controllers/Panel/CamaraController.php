<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Camara;
use App\Models\Modulo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CamaraController extends Controller
{
    public function index(Request $request): View
    {
        $camaras = Camara::with('modulo')
            ->when(in_array($request->input('estado'), ['activo', 'inactivo'], true), fn ($q) => $q->where('habilitada', $request->input('estado') === 'activo'))
            ->when($request->filled('modulo_id'), fn ($q) => $q->where('modulo_id', $request->integer('modulo_id')))
            ->when($request->filled('buscar'), function ($query) use ($request) {
                $buscar = $request->string('buscar');
                $query->where(function ($q) use ($buscar) {
                    $q->where('codigo', 'like', "%{$buscar}%")
                        ->orWhere('nombre', 'like', "%{$buscar}%")
                        ->orWhere('stream_key', 'like', "%{$buscar}%")
                      ->orWhereHas('modulo', fn ($modulo) => $modulo->where('codigo', 'like', "%{$buscar}%")->orWhere('nombre', 'like', "%{$buscar}%"));
                });
            })
            ->orderBy('modulo_id')
            ->orderBy('codigo')
            ->paginate(20)
            ->withQueryString();

        $modulos = Modulo::orderBy('codigo')->get();

        return view('panel.camaras.index', compact('camaras', 'modulos'));
    }

    public function create(): View
    {
        $camara = new Camara(['habilitada' => true, 'tipo' => 'ip']);
        $modulos = Modulo::orderBy('codigo')->get();

        return view('panel.camaras.create', compact('camara', 'modulos'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        $data['habilitada'] = $request->boolean('habilitada');

        Camara::create($data);

        return redirect()->route('panel.camaras.index')->with('success', 'Cámara registrada correctamente.');
    }

    public function show(Camara $camara): View
    {
        $camara->load('modulo');

        return view('panel.camaras.show', compact('camara'));
    }

    public function edit(Camara $camara): View
    {
        $modulos = Modulo::orderBy('codigo')->get();

        return view('panel.camaras.edit', compact('camara', 'modulos'));
    }

    public function update(Request $request, Camara $camara): RedirectResponse
    {
        $data = $this->validateData($request, $camara);
        $data['habilitada'] = $request->boolean('habilitada');

        $camara->update($data);

        return redirect()->route('panel.camaras.index')->with('success', 'Cámara actualizada correctamente.');
    }

    public function destroy(Camara $camara): RedirectResponse
    {
        $camara->delete();

        return redirect()->route('panel.camaras.index')->with('success', 'Cámara eliminada correctamente.');
    }

    private function validateData(Request $request, ?Camara $camara = null): array
    {
        return $request->validate([
            'modulo_id' => ['required', 'exists:modulos,id'],
            'codigo' => [
                'required', 'string', 'max:40',
                Rule::unique('camaras', 'codigo')
                    ->where(fn ($q) => $q->where('modulo_id', $request->integer('modulo_id')))
                    ->ignore($camara?->id),
            ],
            'nombre' => ['required', 'string', 'max:120'],
            'stream_key' => ['required', 'string', 'max:80', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('camaras', 'stream_key')->ignore($camara?->id)],
            'tipo' => ['required', Rule::in(['ip', 'esp32_cam', 'usb', 'otro'])],
        ]);
    }
}
