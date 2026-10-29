<?php

namespace Tests\Feature;

use App\Models\Actuador;
use App\Models\Modulo;
use App\Models\Sensor;
use App\Models\User;
use App\Services\MotorAlertasAutomaticas;
use App\Services\ProcesadorTelemetriaIot;
use App\Services\SupervisionCalefaccion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SupervisionCalefaccionTest extends TestCase
{
    use RefreshDatabase;

    private function equipo(bool $orden = false): array
    {
        $modulo = Modulo::create(['codigo' => 'MOD-001', 'uid' => 'ESP-001', 'ultimo_contacto' => now()]);
        $actuador = Actuador::create([
            'modulo_id' => $modulo->id, 'codigo' => 'D_CALEF', 'nombre' => 'Calefacción', 'tipo' => 'rele',
            'gpio_pin' => 17, 'activo' => true, 'estado_deseado' => ['on' => $orden],
            'estado_reportado' => ['on' => $orden], 'cambiado_en' => now()->subSeconds(10),
        ]);
        $meta = SupervisionCalefaccion::predeterminados('D_CALEF');
        $meta['calibrado'] = true;
        $meta['revision'] = 123;
        $sensor = Sensor::create([
            'modulo_id' => $modulo->id, 'codigo' => 'S_CONF_CALEF', 'nombre' => 'Verificación calefacción',
            'tipo' => SupervisionCalefaccion::TIPO, 'gpio_pin' => 33, 'activo' => true, 'meta' => $meta,
        ]);
        return [$modulo, $actuador, $sensor];
    }

    private function lectura(Modulo $modulo, int $valor, array $cambios = []): void
    {
        $lectura = array_replace_recursive([
            'codigo' => 'S_CONF_CALEF', 'valor' => $valor, 'calidad' => 'ok', 'medido_en' => now()->toIso8601String(),
            'raw' => ['driver' => 'acs712_ac', 'verificacion_valida' => true, 'revision' => 123, 'edad_muestra_ms' => 100],
        ], $cambios);
        app(ProcesadorTelemetriaIot::class)->procesar($modulo, [$lectura]);
    }

    private function estado(Actuador $actuador): string
    {
        return app(SupervisionCalefaccion::class)->paraActuador($actuador->fresh())['codigo'];
    }

    public function test_no_command_or_ack_is_treated_as_physical_confirmation(): void
    {
        [, $actuador] = $this->equipo();
        $this->assertSame('sin_verificar', $this->estado($actuador));
    }

    public function test_on_and_off_are_verified_only_by_valid_current_samples(): void
    {
        [$modulo, $actuador] = $this->equipo();
        $this->lectura($modulo, 0);
        $this->assertSame('apagada', $this->estado($actuador));
        $actuador->update(['estado_deseado' => ['on' => true]]);
        $this->lectura($modulo, 1);
        $this->assertSame('encendida', $this->estado($actuador));
    }

    public function test_off_failure_opens_one_critical_alert_and_recovers(): void
    {
        [$modulo, $actuador] = $this->equipo();
        $this->lectura($modulo, 1);
        $this->lectura($modulo, 1);
        $this->assertSame('no_apaga', $this->estado($actuador));
        $this->assertSame(1, DB::table('alertas')->where('actuador_id', $actuador->id)->where('severidad', 'critico')->where('estado', 'abierta')->count());
        $this->lectura($modulo, 0);
        $this->assertSame('apagada', $this->estado($actuador));
        $this->assertSame(0, DB::table('alertas')->where('severidad', 'critico')->where('estado', 'abierta')->count());
    }

    public function test_on_failure_generates_a_distinct_warning(): void
    {
        [$modulo, $actuador] = $this->equipo(true);
        $this->lectura($modulo, 0);
        $this->assertSame('no_enciende', $this->estado($actuador));
        $this->assertDatabaseHas('alertas', ['actuador_id' => $actuador->id, 'severidad' => 'advertencia', 'estado' => 'abierta']);
    }

    public function test_ordering_on_does_not_clear_a_previous_failure_to_switch_off(): void
    {
        [$modulo, $actuador] = $this->equipo();
        $this->lectura($modulo, 1);
        $actuador->update(['estado_deseado' => ['on' => true]]);
        $this->lectura($modulo, 1);
        $this->assertDatabaseHas('alertas', ['actuador_id' => $actuador->id, 'severidad' => 'critico', 'estado' => 'abierta']);
    }

    public function test_stale_confirmation_is_unknown_and_does_not_close_a_physical_fault(): void
    {
        [$modulo, $actuador] = $this->equipo();
        $this->lectura($modulo, 1);
        $this->travel(31)->seconds();
        $this->assertSame('sin_verificar', $this->estado($actuador));
        app(MotorAlertasAutomaticas::class)->evaluarModulo($modulo->id);
        $this->assertDatabaseHas('alertas', ['actuador_id' => $actuador->id, 'severidad' => 'critico', 'estado' => 'abierta']);
        $this->assertDatabaseHas('alertas', ['sensor_id' => $modulo->sensores->first()->id, 'severidad' => 'advertencia', 'estado' => 'abierta']);
    }

    public function test_invalid_signal_does_not_confirm_off_or_clear_a_fault(): void
    {
        [$modulo, $actuador] = $this->equipo();
        $this->lectura($modulo, 1);
        $this->lectura($modulo, -1, ['calidad' => 'error', 'raw' => ['verificacion_valida' => false]]);
        $this->assertSame('sin_verificar', $this->estado($actuador));
        $this->assertDatabaseHas('alertas', ['actuador_id' => $actuador->id, 'severidad' => 'critico', 'estado' => 'abierta']);
    }

    public function test_pending_calibration_never_confirms_an_electrical_state(): void
    {
        [$modulo, $actuador, $sensor] = $this->equipo();
        $sensor->update(['meta' => array_replace($sensor->meta, ['calibrado' => false])]);
        $this->lectura($modulo, 0);
        $this->assertSame('sin_calibrar', $this->estado($actuador));
        $this->assertDatabaseCount('alertas', 0);
    }

    public function test_old_revision_and_stale_device_snapshot_are_rejected(): void
    {
        [$modulo, $actuador] = $this->equipo();
        $this->lectura($modulo, 0, ['raw' => ['revision' => 122]]);
        $this->assertSame('sin_verificar', $this->estado($actuador));
        $this->lectura($modulo, 0, ['raw' => ['edad_muestra_ms' => 2000]]);
        $this->assertSame('sin_verificar', $this->estado($actuador));
    }

    public function test_new_order_waits_for_a_post_order_sample(): void
    {
        [$modulo, $actuador] = $this->equipo();
        $actuador->update(['estado_deseado' => ['on' => true], 'cambiado_en' => now()]);
        $this->lectura($modulo, 0);
        $this->assertSame('pendiente', $this->estado($actuador));
        $this->assertSame(0, DB::table('alertas')->where('estado', 'abierta')->count());
        $this->travel(4)->seconds();
        $this->lectura($modulo, 1);
        $this->assertSame('encendida', $this->estado($actuador));
    }

    public function test_delayed_telemetry_cannot_replace_a_newer_confirmation(): void
    {
        [$modulo, $actuador] = $this->equipo();
        $this->lectura($modulo, 0);
        $this->lectura($modulo, 1, ['medido_en' => now()->subMinute()->toIso8601String()]);
        $this->assertSame('apagada', $this->estado($actuador));
        $this->assertDatabaseCount('lecturas', 1);
    }

    public function test_missing_acquisition_time_cannot_confirm_off(): void
    {
        [$modulo, $actuador] = $this->equipo();
        $this->lectura($modulo, 0, ['medido_en' => null]);
        $this->assertSame('sin_verificar', $this->estado($actuador));
    }

    public function test_mqtt_and_rest_share_the_same_verification_path(): void
    {
        [$modulo, $actuador] = $this->equipo();
        config(['mqtt.webhook_secret' => 'test-secret']);
        $this->postJson('/api/mqtt/v1/webhook', [
            'topic' => 'mecacuy/MOD-001/telemetry',
            'payload' => ['lecturas' => [[
                'codigo' => 'S_CONF_CALEF', 'valor' => 0, 'calidad' => 'ok', 'medido_en' => now()->toIso8601String(),
                'raw' => ['driver' => 'acs712_ac', 'verificacion_valida' => true, 'revision' => 123, 'edad_muestra_ms' => 50],
            ]]],
        ], ['X-MECACUY-MQTT-SECRET' => 'test-secret'])->assertOk();
        $this->assertSame('apagada', $this->estado($actuador));
    }

    public function test_web_status_is_authenticated_and_contains_no_current_value(): void
    {
        [$modulo, $actuador] = $this->equipo();
        $this->lectura($modulo, 0);
        $url = route('panel.actuadores.verificacion', $actuador);
        $this->getJson($url)->assertUnauthorized();
        $this->actingAs(User::factory()->create(['rol' => 'lector']))->getJson($url)
            ->assertOk()->assertJsonPath('codigo', 'apagada')->assertJsonMissingPath('corriente');
    }

    public function test_module_and_actuator_views_render_verification_without_amperes(): void
    {
        [$modulo, $actuador] = $this->equipo();
        $this->lectura($modulo, 0);
        $this->actingAs(User::factory()->create(['rol' => 'admin']));
        $this->get(route('panel.modulos.show', $modulo))->assertOk()->assertSee('Apagado eléctrico verificado');
        $this->get(route('panel.actuadores.index'))->assertOk()->assertSee('Apagado eléctrico verificado');
        $this->get(route('panel.sensores.edit', $modulo->sensores->first()))->assertOk()->assertSee('Sensor instalado y calibrado');
    }

    public function test_setup_command_preserves_existing_calibration_and_checks_pin_conflicts(): void
    {
        [$modulo, , $sensor] = $this->equipo();
        $this->artisan('mecacuy:calefaccion-configurar', ['modulo' => $modulo->codigo])->assertSuccessful();
        $this->assertTrue($sensor->fresh()->meta['calibrado']);
        $sensor->delete();
        Sensor::create(['modulo_id' => $modulo->id, 'codigo' => 'OTRO', 'nombre' => 'Ocupado', 'tipo' => 'otro', 'gpio_pin' => 33]);
        $this->artisan('mecacuy:calefaccion-configurar', ['modulo' => $modulo->codigo])->assertFailed();
    }

    public function test_setup_command_registers_an_uncalibrated_sensor_without_switching_heater(): void
    {
        [$modulo, $actuador, $sensor] = $this->equipo(true);
        $sensor->delete();
        $this->artisan('mecacuy:calefaccion-configurar', ['modulo' => $modulo->codigo])->assertSuccessful();
        $this->assertFalse($modulo->sensores()->first()->meta['calibrado']);
        $this->assertSame(['on' => true], $actuador->fresh()->estado_deseado);
        $this->assertDatabaseCount('comandos_iot', 0);
    }

    public function test_form_rejects_a_gpio_used_by_another_device(): void
    {
        [$modulo, , $sensor] = $this->equipo();
        $this->actingAs(User::factory()->create(['rol' => 'admin']));
        $this->put(route('panel.sensores.update', $sensor), [
            'modulo_id' => $modulo->id, 'codigo' => $sensor->codigo, 'nombre' => $sensor->nombre,
            'tipo' => SupervisionCalefaccion::TIPO, 'gpio_pin' => 17, 'activo' => 1,
            'calefaccion_actuador' => 'D_CALEF', 'calefaccion_calibrado' => 0,
            'calefaccion_umbral_encendido_mv' => 80, 'calefaccion_umbral_apagado_mv' => 40,
            'calefaccion_cero_min_mv' => 1300, 'calefaccion_cero_max_mv' => 2000,
            'calefaccion_cero_mv' => 1667,
            'calefaccion_espera_seg' => 3, 'calefaccion_vigencia_seg' => 30,
        ])->assertSessionHasErrors('gpio_pin');
    }
}
