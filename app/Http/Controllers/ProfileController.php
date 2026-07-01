<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\Modulo;
use App\Models\ModuloCredencial;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        $modulosIot = collect();

        if (($request->user()->rol ?? null) === 'admin') {
            $modulosIot = Modulo::query()
                ->with('credencial')
                ->orderBy('codigo')
                ->get()
                ->map(function (Modulo $modulo) {
                    $apiKeyVisible = null;

                    if ($modulo->credencial?->api_key_encrypted) {
                        try {
                            $apiKeyVisible = Crypt::decryptString($modulo->credencial->api_key_encrypted);
                        } catch (\Throwable $exception) {
                            $apiKeyVisible = null;
                        }
                    }

                    $modulo->setAttribute('api_key_visible', $apiKeyVisible);

                    return $modulo;
                });
        }

        return view('profile.edit', [
            'user' => $request->user(),
            'modulosIot' => $modulosIot,
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Regenerate the API key used by an ESP32 module.
     */
    public function regenerateIotKey(Request $request, Modulo $modulo): RedirectResponse
    {
        abort_unless(($request->user()->rol ?? null) === 'admin', 403);

        $newKey = 'mc_' . Str::random(56);

        ModuloCredencial::query()->updateOrCreate(
            ['modulo_id' => $modulo->id],
            [
                'api_key_hash' => Hash::make($newKey),
                'api_key_encrypted' => Crypt::encryptString($newKey),
                'revocado_en' => null,
                'ultimo_uso_en' => null,
            ]
        );

        return Redirect::route('profile.edit')
            ->with('status', 'iot-key-regenerated')
            ->with('iot_key_modulo', $modulo->codigo)
            ->with('iot_key_uid', $modulo->uid)
            ->with('iot_key_plain', $newKey);
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
