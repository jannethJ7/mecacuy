<?php

namespace Tests\Feature;

use App\Models\Actuador;
use App\Models\Modulo;
use App\Models\ReglaAutomatica;
use App\Models\Sensor;
use App\Services\MotorReglasAutomaticas;
use App\Services\ProcesadorAckIot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IotSafetyTest extends TestCase
{
    use RefreshDatabase;

    private function devices(): array
    {
        $modulo = Modulo::create(['codigo' => 'MOD-TEST', 'uid' => 'ESP-TEST', 'habilitado' => true]);
        $actuador = Actuador::create([
            'modulo_id' => $modulo->id, 'codigo' => 'D_FAN', 'nombre' => 'Ventilador',
            'tipo' => 'rele', 'activo' => true,
            'estado_deseado' => ['on' => false], 'estado_reportado' => ['on' => false],
        ]);
        return [$modulo, $actuador];
    }

    private function command(Modulo $modulo, Actuador $actuador, string $nonce): int
    {
        return DB::table('comandos_iot')->insertGetId([
            'modulo_id' => $modulo->id, 'actuador_id' => $actuador->id,
            'nonce' => $nonce, 'tipo' => 'set_estado', 'payload' => '{}',
            'estado' => 'enviado', 'intentos' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_ack_preserves_newer_desired_state_and_duplicate_is_idempotent(): void
    {
        [$modulo, $actuador] = $this->devices();
        $this->command($modulo, $actuador, 'old-command');
        $reports = [['actuador' => 'D_FAN', 'estado' => ['on' => true]]];
        $processor = app(ProcesadorAckIot::class);
        $this->assertTrue($processor->procesar($modulo, 'old-command', true, null, $reports)['ok']);
        $this->assertSame(['on' => false], $actuador->fresh()->estado_deseado);
        $this->assertSame(['on' => true], $actuador->fresh()->estado_reportado);
        $duplicate = $processor->procesar($modulo, 'old-command', true, null, $reports);
        $this->assertTrue($duplicate['ack']['duplicado']);
        $this->assertDatabaseCount('actuaciones', 1);
    }

    public function test_unknown_nonce_does_not_change_actuator(): void
    {
        [$modulo, $actuador] = $this->devices();
        $result = app(ProcesadorAckIot::class)->procesar($modulo, 'unknown', true, null,
            [['actuador' => 'D_FAN', 'estado' => ['on' => true]]]);
        $this->assertFalse($result['ok']);
        $this->assertSame(['on' => false], $actuador->fresh()->estado_reportado);
        $this->assertDatabaseCount('actuaciones', 0);
    }

    public function test_contradictory_ack_cannot_reopen_confirmed_command(): void
    {
        [$modulo, $actuador] = $this->devices();
        $id = $this->command($modulo, $actuador, 'confirmed-command');
        DB::table('comandos_iot')->where('id', $id)->update(['estado' => 'confirmado']);
        $result = app(ProcesadorAckIot::class)->procesar($modulo, 'confirmed-command', false);
        $this->assertFalse($result['ok']);
        $this->assertDatabaseHas('comandos_iot', ['id' => $id, 'estado' => 'confirmado']);
    }

    public function test_old_ack_does_not_revert_newer_confirmed_report(): void
    {
        [$modulo, $actuador] = $this->devices();
        $this->command($modulo, $actuador, 'older');
        $newer = $this->command($modulo, $actuador, 'newer');
        DB::table('comandos_iot')->where('id', $newer)->update(['estado' => 'confirmado']);
        app(ProcesadorAckIot::class)->procesar($modulo, 'older', true, null,
            [['actuador' => 'D_FAN', 'estado' => ['on' => true]]]);
        $this->assertSame(['on' => false], $actuador->fresh()->estado_reportado);
    }

    public function test_stale_reading_cannot_trigger_rule_but_fresh_reading_can(): void
    {
        config(['mqtt.enabled' => false]);
        [$modulo, $actuador] = $this->devices();
        DB::table('config_sistema')->insert([
            'clave' => 'modo_global', 'valor' => json_encode('automatico'),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $sensor = Sensor::create([
            'modulo_id' => $modulo->id, 'codigo' => 'S_TEMP', 'nombre' => 'Temperatura',
            'tipo' => 'temperatura', 'activo' => true,
            'valor_actual' => 40, 'valor_actual_en' => now()->subMinutes(11),
        ]);
        ReglaAutomatica::create([
            'modulo_id' => $modulo->id, 'sensor_id' => $sensor->id,
            'actuador_id' => $actuador->id, 'nombre' => 'Temperatura alta', 'activo' => true,
            'objetivo_max' => 30, 'histeresis' => 0, 'retardo_seg' => 0,
        ]);
        $motor = app(MotorReglasAutomaticas::class);
        $result = $motor->evaluarModulo($modulo->id);
        $this->assertSame(0, $result['comandos_creados']);
        $this->assertSame('lectura_vencida_o_fecha_invalida', $result['omitidas'][0]['motivo']);
        $sensor->update(['valor_actual_en' => now()]);
        $this->assertSame(1, $motor->evaluarModulo($modulo->id)['comandos_creados']);
    }

    public function test_mqtt_rejects_string_false_and_invalid_telemetry_without_side_effects(): void
    {
        [$modulo, $actuador] = $this->devices();
        $id = $this->command($modulo, $actuador, 'mqtt-command');
        config(['mqtt.webhook_secret' => 'test-secret']);
        $headers = ['X-MECACUY-MQTT-SECRET' => 'test-secret'];
        $this->postJson('/api/mqtt/v1/webhook', [
            'topic' => 'mecacuy/MOD-TEST/ack',
            'payload' => ['nonce' => 'mqtt-command', 'ok' => 'false'],
        ], $headers)->assertUnprocessable()->assertJsonValidationErrors('ok');
        $this->assertDatabaseHas('comandos_iot', ['id' => $id, 'estado' => 'enviado']);
        $this->postJson('/api/mqtt/v1/webhook', [
            'topic' => 'mecacuy/MOD-TEST/telemetry',
            'payload' => ['lecturas' => [['codigo' => 'S_TEMP', 'valor' => 20, 'medido_en' => 'invalid']]],
        ], $headers)->assertUnprocessable()->assertJsonValidationErrors('lecturas.0.medido_en');
        $this->assertNull($modulo->fresh()->ultimo_contacto);
        $this->assertDatabaseCount('lecturas', 0);
    }
}
