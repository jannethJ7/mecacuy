<?php

namespace Tests\Feature;

use App\Models\Camara;
use App\Models\Modulo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModuleCameraViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_module_without_camera_has_an_honest_empty_state(): void
    {
        $user = User::factory()->create(['rol' => 'admin']);
        $modulo = Modulo::create(['codigo' => 'MOD-A', 'uid' => 'ESP-A']);
        $this->actingAs($user)->get(route('panel.modulos.show', $modulo))
            ->assertOk()->assertSee('Cámara de la jaula')
            ->assertSee('No hay cámaras habilitadas en este módulo')
            ->assertSee('Esquema de la jaula');
    }

    public function test_camera_selector_only_includes_enabled_cameras_of_the_module(): void
    {
        $user = User::factory()->create(['rol' => 'operador']);
        $modulo = Modulo::create(['codigo' => 'MOD-A', 'uid' => 'ESP-A']);
        $other = Modulo::create(['codigo' => 'MOD-B', 'uid' => 'ESP-B']);
        foreach ([[$modulo, 'Disponible', true], [$modulo, 'Deshabilitada', false], [$other, 'Otra jaula', true]] as $index => [$owner, $name, $enabled]) {
            Camara::create([
                'modulo_id' => $owner->id, 'codigo' => 'CAM-'.$index, 'nombre' => $name,
                'stream_key' => 'cam-'.$index, 'tipo' => 'ip', 'habilitada' => $enabled,
            ]);
        }
        $this->actingAs($user)->get(route('panel.modulos.show', $modulo))
            ->assertOk()->assertSee('Disponible')->assertDontSee('Deshabilitada')
            ->assertDontSee('Otra jaula')->assertSee('data-source=', false);
    }
}
