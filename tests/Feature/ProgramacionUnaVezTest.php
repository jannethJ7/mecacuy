<?php

namespace Tests\Feature;

use App\Models\Actuador;
use App\Models\ComandoIot;
use App\Models\EjecucionProgramacion;
use App\Models\Modulo;
use App\Models\Programacion;
use App\Services\MotorProgramaciones;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProgramacionUnaVezTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        config(['mqtt.enabled' => false, 'app.timezone' => 'America/La_Paz']);
        Http::preventStrayRequests();
        DB::table('config_sistema')->insert(['clave' => 'modo_global', 'valor' => json_encode('automatico'),
            'created_at' => now(), 'updated_at' => now()]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function fixture(): array
    {
        $modulo = Modulo::create(['codigo' => 'MOD-TEST', 'uid' => 'ESP-TEST',
            'habilitado' => true, 'zona_horaria' => 'America/La_Paz']);
        $actuador = Actuador::create(['modulo_id' => $modulo->id, 'codigo' => 'D_FEED',
            'nombre' => 'Dosificador', 'tipo' => 'dosificador', 'activo' => true,
            'estado_deseado' => ['on' => false]]);
        $programacion = Programacion::create(['modulo_id' => $modulo->id, 'actuador_id' => $actuador->id,
            'nombre' => 'Alimento 20 s', 'activo' => true, 'dias' => ['lu','ma','mi','ju','vi','sa','do'],
            'hora_inicio' => '05:35:00', 'duracion_seg' => 20, 'prioridad' => 10,
            'estado_deseado' => ['on' => true, 'accion' => 'pulso', 'duracion_seg' => 20]]);
        return [$modulo, $programacion];
    }

    private function evaluate(Modulo $modulo, string $time): array
    {
        $now = Carbon::parse($time, 'America/La_Paz');
        Carbon::setTestNow($now);
        return app(MotorProgramaciones::class)->evaluarModulo($modulo->id, $now);
    }

    public function test_repeated_sync_calls_create_one_start_and_one_stop(): void
    {
        [$modulo] = $this->fixture();
        $this->assertSame(0, $this->evaluate($modulo, '2026-10-30 05:34:59')['inicios_creados']);
        for ($second = 0; $second < 90; $second += 3) {
            $this->evaluate($modulo, Carbon::parse('2026-10-30 05:35:00')->addSeconds($second)->toDateTimeString());
        }
        $this->assertDatabaseCount('ejecuciones_programacion', 1);
        $this->assertSame(1, ComandoIot::where('payload->fase', 'programacion_inicio')->count());
        $this->assertSame(1, ComandoIot::where('payload->fase', 'programacion_fin')->count());
        $this->assertSame('2026-10-30 05:35:00', EjecucionProgramacion::first()->getRawOriginal('inicio_en'));
        $this->assertSame(20, ComandoIot::where('payload->fase', 'programacion_inicio')->first()->payload['duracion_seg']);
    }

    public function test_same_schedule_can_run_the_next_day(): void
    {
        [$modulo] = $this->fixture();
        $this->evaluate($modulo, '2026-10-30 05:35:00');
        $this->evaluate($modulo, '2026-10-30 05:35:21');
        $result = $this->evaluate($modulo, '2026-10-31 05:35:00');
        $this->assertSame(1, $result['inicios_creados']);
        $this->assertDatabaseCount('ejecuciones_programacion', 2);
    }

    public function test_command_history_blocks_a_slot_with_legacy_corrupted_start(): void
    {
        [$modulo] = $this->fixture();
        $this->evaluate($modulo, '2026-10-30 05:35:00');
        EjecucionProgramacion::query()->update(['inicio_en' => '2026-10-30 09:35:21', 'fin_en' => '2026-10-30 05:35:20']);
        $result = $this->evaluate($modulo, '2026-10-30 05:35:24');
        $this->assertSame(0, $result['inicios_creados']);
        $this->assertDatabaseCount('ejecuciones_programacion', 1);
        $this->assertDatabaseCount('comandos_iot', 1);
    }

    public function test_finishing_explicitly_preserves_start_in_update_sql(): void
    {
        [$modulo] = $this->fixture();
        $this->evaluate($modulo, '2026-10-30 05:35:00');
        DB::enableQueryLog(); DB::flushQueryLog();
        $this->evaluate($modulo, '2026-10-30 05:35:20');
        $updates = array_filter(DB::getQueryLog(), fn ($q) => str_starts_with(strtolower($q['query']), 'update "ejecuciones_programacion"'));
        DB::disableQueryLog();
        $this->assertNotEmpty($updates);
        foreach ($updates as $query) $this->assertStringContainsString('"inicio_en" =', $query['query']);
    }

    public function test_late_evaluation_reserves_duration_from_command_creation(): void
    {
        [$modulo] = $this->fixture();
        $this->evaluate($modulo, '2026-10-30 05:35:15');
        $this->assertSame(0, $this->evaluate($modulo, '2026-10-30 05:35:21')['finalizaciones_creadas']);
        $this->assertSame(1, $this->evaluate($modulo, '2026-10-30 05:35:35')['finalizaciones_creadas']);
        $this->assertSame('2026-10-30 05:35:35', EjecucionProgramacion::first()->getRawOriginal('fin_en'));
    }

    public function test_pending_stop_runs_in_manual_mode_once(): void
    {
        [$modulo] = $this->fixture();
        $this->evaluate($modulo, '2026-10-30 05:35:00');
        DB::table('config_sistema')->where('clave', 'modo_global')->update(['valor' => json_encode('manual')]);
        $this->assertSame(1, $this->evaluate($modulo, '2026-10-30 05:35:21')['finalizaciones_creadas']);
        $this->assertSame(0, $this->evaluate($modulo, '2026-10-30 05:35:24')['finalizaciones_creadas']);
        $this->assertDatabaseCount('comandos_iot', 2);
    }
}
