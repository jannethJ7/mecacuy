<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('modulos', function (Blueprint $table) {
            $table->id();

            // Identificación humana
            $table->string('codigo', 50)->unique();   // MOD-001, JAULA-01
            $table->string('nombre', 120)->nullable();

            // Identidad única del ESP32 (MAC/ChipId/UID que mande firmware)
            $table->string('uid', 100)->unique();

            $table->boolean('habilitado')->default(true);

            // Telemetría
            $table->string('version_firmware', 40)->nullable();
            $table->string('ip', 45)->nullable();
            $table->smallInteger('rssi')->nullable();
            $table->timestamp('ultimo_contacto')->nullable();

            $table->string('zona_horaria', 60)->default('America/La_Paz');
            $table->json('meta')->nullable();

            $table->timestamps();
        });

        Schema::create('modulos_credenciales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('modulo_id')->constrained('modulos')->cascadeOnDelete();

            $table->string('api_key_hash'); // guardar hash (bcrypt/argon)
            $table->timestamp('revocado_en')->nullable();
            $table->timestamp('ultimo_uso_en')->nullable();

            $table->timestamps();
            $table->unique('modulo_id');
        });

        Schema::create('sensores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('modulo_id')->constrained('modulos')->cascadeOnDelete();

            // Código estable para firmware: S_TEMP, S_HR, S_AIR...
            $table->string('codigo', 40);
            $table->string('nombre', 120);

            // temperatura | humedad | calidad_aire | otro
            $table->string('tipo', 40);

            $table->string('unidad', 20)->nullable(); // °C, %, ppm...
            $table->boolean('activo')->default(true);

            $table->decimal('valor_actual', 10, 3)->nullable();
            $table->timestamp('valor_actual_en')->nullable();

            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['modulo_id', 'codigo']);
            $table->index(['modulo_id', 'tipo']);
        });

        Schema::create('actuadores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('modulo_id')->constrained('modulos')->cascadeOnDelete();

            // Código estable para firmware: D_FAN, D_HEAT, D_WATER, D_FEED...
            $table->string('codigo', 40);
            $table->string('nombre', 120);

            // rele | pwm | dosificador | valvula | stepper | otro
            $table->string('tipo', 40);

            $table->boolean('activo')->default(true);

            // Para relés simples
            $table->integer('gpio_pin')->nullable();
            $table->boolean('invertido')->default(false); // relé active-low

            // Estado deseado vs reportado
            $table->json('estado_deseado')->nullable();
            $table->json('estado_reportado')->nullable();
            $table->timestamp('cambiado_en')->nullable();

            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['modulo_id', 'codigo']);
            $table->index(['modulo_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('actuadores');
        Schema::dropIfExists('sensores');
        Schema::dropIfExists('modulos_credenciales');
        Schema::dropIfExists('modulos');
    }
};