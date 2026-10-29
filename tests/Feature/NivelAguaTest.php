<?php

namespace Tests\Feature;

use App\Models\Actuador;
use App\Models\Modulo;
use App\Models\ReglaAutomatica;
use App\Models\Sensor;
use App\Models\User;
use App\Services\NivelAgua;
use App\Services\ProcesadorTelemetriaIot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class NivelAguaTest extends TestCase
{
    use RefreshDatabase;

    private function modulo(): Modulo
    {
        return Modulo::create(['codigo' => 'MOD-001', 'uid' => 'ESP-001', 'ultimo_contacto' => now()]);
    }

    private function individuales(Modulo $modulo): array
    {
        $sensores = [];
        foreach (NivelAgua::PINES as $nivel => $gpio) {
            $sensores[$nivel] = Sensor::create(['modulo_id' => $modulo->id, 'codigo' => 'S_NIVEL_'.$nivel,
                'nombre' => 'Nivel '.$nivel, 'tipo' => 'temperatura', 'gpio_pin' => $gpio, 'activo' => true]);
        }
        return $sensores;
    }

    private function recibir(Modulo $modulo, int $valor, array $cambios = []): array
    {
        return app(ProcesadorTelemetriaIot::class)->procesar($modulo, [array_replace_recursive([
            'codigo' => 'S_NIVEL', 'valor' => $valor, 'calidad' => 'ok', 'medido_en' => now()->toIso8601String(),
            'raw' => ['driver' => NivelAgua::DRIVER, 'perfil' => NivelAgua::PERFIL, 'nivel_valido' => true, 'edad_muestra_ms' => 50],
        ], $cambios)]);
    }

    public function test_setup_consolidates_four_inputs_without_deleting_history(): void
    {
        $modulo = $this->modulo();
        $anteriores = $this->individuales($modulo);
        $anteriores[25]->lecturas()->create(['valor' => 1, 'medido_en' => now(), 'recibido_en' => now(), 'calidad' => 'ok']);
        $this->artisan('mecacuy:agua-configurar', ['modulo' => 'MOD-001'])->assertSuccessful();
        $sensor = $modulo->sensores()->where('codigo', 'S_NIVEL')->firstOrFail();
        $this->assertNull($sensor->gpio_pin);
        $this->assertSame('%', $sensor->unidad);
        $this->assertSame(NivelAgua::PERFIL, $sensor->meta['perfil']);
        $this->assertSame(1, $modulo->sensores()->where('activo', true)->count());
        $this->assertDatabaseCount('lecturas', 1);
        $this->assertDatabaseCount('comandos_iot', 0);
    }

    public function test_setup_is_idempotent_and_preserves_a_valid_aggregate_reading(): void
    {
        $modulo = $this->modulo();
        app(NivelAgua::class)->configurar($modulo);
        $this->recibir($modulo, 75);
        app(NivelAgua::class)->configurar($modulo);
        $this->assertDatabaseCount('sensores', 1);
        $this->assertSame('75.000', $modulo->sensores()->first()->valor_actual);
    }

    public function test_existing_binary_low_level_rule_is_converted_to_percent(): void
    {
        $modulo = $this->modulo();
        $anteriores = $this->individuales($modulo);
        $actuador = Actuador::create(['modulo_id' => $modulo->id, 'codigo' => 'D_AGUA', 'nombre' => 'Agua', 'tipo' => 'valvula', 'gpio_pin' => 18]);
        $regla = ReglaAutomatica::create(['modulo_id' => $modulo->id, 'sensor_id' => $anteriores[50]->id,
            'actuador_id' => $actuador->id, 'nombre' => 'Rellenar bajo 50', 'activo' => true,
            'objetivo_min' => .5, 'histeresis' => 0]);
        $resultado = app(NivelAgua::class)->configurar($modulo);
        $this->assertSame([$regla->id], $resultado['reglas_convertidas']);
        $this->assertSame('50.000', $regla->fresh()->objetivo_min);
        $this->assertSame($modulo->sensores()->where('codigo', 'S_NIVEL')->value('id'), $regla->fresh()->sensor_id);
        $this->assertTrue($regla->fresh()->activo);
    }

    public function test_ambiguous_legacy_rule_is_disabled_for_review_instead_of_reinterpreted(): void
    {
        $modulo = $this->modulo();
        $anteriores = $this->individuales($modulo);
        $actuador = Actuador::create(['modulo_id' => $modulo->id, 'codigo' => 'D_AGUA', 'nombre' => 'Agua', 'tipo' => 'valvula', 'gpio_pin' => 18]);
        $regla = ReglaAutomatica::create(['modulo_id' => $modulo->id, 'sensor_id' => $anteriores[50]->id,
            'actuador_id' => $actuador->id, 'nombre' => 'Personalizada', 'activo' => true, 'objetivo_min' => 5]);
        $resultado = app(NivelAgua::class)->configurar($modulo);
        $this->assertSame([$regla->id], $resultado['reglas_por_revisar']);
        $this->assertFalse($regla->fresh()->activo);
    }

    public function test_setup_rejects_actuator_conflicts_without_moving_any_pin(): void
    {
        $modulo = $this->modulo();
        $actuador = Actuador::create(['modulo_id' => $modulo->id, 'codigo' => 'D_FAN', 'nombre' => 'Ventilador', 'tipo' => 'rele', 'gpio_pin' => 23]);
        $this->artisan('mecacuy:agua-configurar', ['modulo' => 'MOD-001'])->assertFailed();
        $this->assertSame(23, $actuador->fresh()->gpio_pin);
        $this->assertDatabaseCount('sensores', 0);
    }

    public function test_all_five_percentages_are_received_as_one_sensor(): void
    {
        $modulo = $this->modulo();
        app(NivelAgua::class)->configurar($modulo);
        foreach ([0, 25, 50, 75, 100] as $valor) {
            $this->recibir($modulo, $valor);
            $this->assertEquals($valor, (float) $modulo->sensores()->first()->valor_actual);
        }
        $this->assertDatabaseCount('lecturas', 5);
    }

    public function test_invalid_or_unfiltered_water_values_do_not_trigger_control_as_empty(): void
    {
        $modulo = $this->modulo();
        app(NivelAgua::class)->configurar($modulo);
        $this->recibir($modulo, 75);
        $this->recibir($modulo, -1, ['calidad' => 'error', 'raw' => ['nivel_valido' => false]]);
        $this->assertNull($modulo->sensores()->first()->valor_actual);
        $this->recibir($modulo, 65);
        $this->assertNull($modulo->sensores()->first()->valor_actual);
        $this->assertDatabaseCount('comandos_iot', 0);
    }

    public function test_old_water_message_cannot_replace_a_newer_level(): void
    {
        $modulo = $this->modulo();
        app(NivelAgua::class)->configurar($modulo);
        $this->recibir($modulo, 100);
        $resultado = $this->recibir($modulo, 0, ['medido_en' => now()->subMinute()->toIso8601String()]);
        $this->assertSame(0, $resultado['guardadas']);
        $this->assertEquals(100, (float) $modulo->sensores()->first()->valor_actual);
    }

    public function test_form_configures_one_set_and_ignores_submitted_gpio_or_profile(): void
    {
        $modulo = $this->modulo();
        $this->actingAs(User::factory()->create(['rol' => 'admin']))->post(route('panel.sensores.store'), [
            'modulo_id' => $modulo->id, 'nombre' => 'Tanque principal', 'tipo' => 'nivel_agua', 'activo' => 1,
            'codigo' => 'OTRO', 'gpio_pin' => 4, 'perfil_nivel_agua' => 'alterado',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $sensor = $modulo->sensores()->firstOrFail();
        $this->assertSame('S_NIVEL', $sensor->codigo);
        $this->assertNull($sensor->gpio_pin);
        $this->assertSame(NivelAgua::PERFIL, $sensor->meta['perfil']);
    }

    public function test_other_sensors_cannot_take_a_reserved_tank_input(): void
    {
        $modulo = $this->modulo();
        app(NivelAgua::class)->configurar($modulo);
        $this->actingAs(User::factory()->create(['rol' => 'admin']))->post(route('panel.sensores.store'), [
            'modulo_id' => $modulo->id, 'nombre' => 'Otro sensor', 'codigo' => 'OTRO', 'tipo' => 'otro', 'gpio_pin' => 34, 'activo' => 1,
        ])->assertSessionHasErrors('gpio_pin');
    }

    public function test_other_actuators_cannot_take_a_reserved_tank_input(): void
    {
        $modulo = $this->modulo();
        app(NivelAgua::class)->configurar($modulo);
        $this->actingAs(User::factory()->create(['rol' => 'admin']))->post(route('panel.actuadores.store'), [
            'modulo_id' => $modulo->id, 'nombre' => 'Otro relé', 'codigo' => 'RELE', 'tipo' => 'rele', 'gpio_pin' => 27, 'activo' => 1,
        ])->assertSessionHasErrors('gpio_pin');
    }

    public function test_sensor_list_shows_the_set_and_keeps_legacy_entries_out_of_configuration(): void
    {
        $modulo = $this->modulo();
        $this->individuales($modulo);
        app(NivelAgua::class)->configurar($modulo);
        $this->recibir($modulo, 50);
        $this->actingAs(User::factory()->create(['rol' => 'admin']))->get(route('panel.sensores.index'))
            ->assertOk()->assertSee('S_NIVEL')->assertSee('Conjunto del tanque')->assertDontSee('S_NIVEL_25')->assertDontSee('S_NIVEL_100');
        $this->get(route('panel.modulos.show', $modulo))->assertOk()->assertSee('Nivel de agua');
    }

    public function test_edit_form_keeps_heating_supervision_and_adds_simple_water_assignment(): void
    {
        $modulo = $this->modulo();
        app(NivelAgua::class)->configurar($modulo);
        $this->actingAs(User::factory()->create(['rol' => 'admin']))->get(route('panel.sensores.edit', $modulo->sensores()->first()))
            ->assertOk()->assertSee('Una sola asignación')->assertSee('no se configuran por separado');
    }
}
