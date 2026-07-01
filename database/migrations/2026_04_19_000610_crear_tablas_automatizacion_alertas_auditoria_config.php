<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('reglas_automaticas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('modulo_id')->constrained('modulos')->cascadeOnDelete();

            $table->foreignId('sensor_id')->constrained('sensores')->cascadeOnDelete();
            $table->foreignId('actuador_id')->constrained('actuadores')->cascadeOnDelete();

            $table->string('nombre', 160);
            $table->boolean('activo')->default(true);

            // Rango objetivo (ideal para T°, HR, aire)
            $table->decimal('objetivo_min', 10, 3)->nullable();
            $table->decimal('objetivo_max', 10, 3)->nullable();

            // Anti-oscilación
            $table->decimal('histeresis', 10, 3)->default(0);
            $table->unsignedInteger('retardo_seg')->default(0); // hold_seconds

            $table->json('payload')->nullable();
            $table->integer('prioridad')->default(100);

            $table->timestamps();

            $table->index(['modulo_id', 'activo']);
            $table->index(['actuador_id', 'activo']);
        });

        Schema::create('estado_reglas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('regla_id')->constrained('reglas_automaticas')->cascadeOnDelete();

            $table->json('estado_latch')->nullable();
            $table->timestamp('cambiado_en')->nullable();
            $table->timestamp('evaluado_en')->nullable();
            $table->timestamp('bloqueado_hasta')->nullable();

            $table->timestamps();
            $table->unique('regla_id');
        });

        Schema::create('programaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('modulo_id')->constrained('modulos')->cascadeOnDelete();
            $table->foreignId('actuador_id')->constrained('actuadores')->cascadeOnDelete();

            $table->string('nombre', 160);
            $table->boolean('activo')->default(true);

            $table->json('dias');               // ["lun","mar","mie"...]
            $table->time('hora_inicio');
            $table->unsignedInteger('duracion_seg');

            $table->json('estado_deseado');      // {"on":true} o {"cantidad_g":30}
            $table->integer('prioridad')->default(50);

            $table->timestamps();

            $table->index(['modulo_id', 'activo']);
            $table->index(['actuador_id', 'activo']);
        });

        Schema::create('ejecuciones_programacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programacion_id')->constrained('programaciones')->cascadeOnDelete();

            $table->timestamp('inicio_en');
            $table->timestamp('fin_en')->nullable();
            $table->enum('estado', ['ok', 'omitido', 'fallido'])->default('ok');
            $table->string('nota', 255)->nullable();

            $table->timestamps();
            $table->index(['programacion_id', 'inicio_en']);
        });

        Schema::create('reglas_alerta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('modulo_id')->constrained('modulos')->cascadeOnDelete();

            $table->foreignId('sensor_id')->nullable()->constrained('sensores')->nullOnDelete();
            $table->foreignId('actuador_id')->nullable()->constrained('actuadores')->nullOnDelete();

            // arriba | abajo | fuera_rango | sin_datos | modulo_offline | comando_fallido
            $table->string('tipo', 40);

            $table->decimal('umbral_min', 10, 3)->nullable();
            $table->decimal('umbral_max', 10, 3)->nullable();
            $table->unsignedInteger('sin_datos_min')->nullable();

            $table->enum('severidad', ['info', 'advertencia', 'critico'])->default('advertencia');
            $table->string('plantilla_mensaje', 255);
            $table->unsignedInteger('enfriamiento_min')->default(10);
            $table->boolean('activo')->default(true);

            $table->timestamps();

            $table->index(['modulo_id', 'activo']);
            $table->index(['severidad', 'activo']);
        });

        Schema::create('alertas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('modulo_id')->constrained('modulos')->cascadeOnDelete();

            $table->foreignId('regla_alerta_id')->nullable()->constrained('reglas_alerta')->nullOnDelete();
            $table->foreignId('sensor_id')->nullable()->constrained('sensores')->nullOnDelete();
            $table->foreignId('actuador_id')->nullable()->constrained('actuadores')->nullOnDelete();

            $table->enum('severidad', ['info', 'advertencia', 'critico'])->default('advertencia');
            $table->string('mensaje', 255);
            $table->json('contexto')->nullable();

            // abierta | reconocida | cerrada
            $table->enum('estado', ['abierta', 'reconocida', 'cerrada'])->default('abierta');

            $table->foreignId('reconocida_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reconocida_en')->nullable();
            $table->timestamp('cerrada_en')->nullable();

            $table->timestamps();

            $table->index(['modulo_id', 'estado', 'created_at']);
            $table->index(['severidad', 'created_at']);
        });

        Schema::create('auditoria_eventos', function (Blueprint $table) {
            $table->id();

            // user | modulo | sistema
            $table->string('actor_tipo', 20);
            $table->unsignedBigInteger('actor_id')->nullable();

            $table->string('evento_tipo', 80);   // ej: modulo.sync, regla.aplicada, comando.confirmado
            $table->string('entidad_tipo', 40)->nullable(); // sensor, actuador, regla, alerta...
            $table->unsignedBigInteger('entidad_id')->nullable();

            $table->json('data')->nullable();
            $table->timestamp('creado_en')->useCurrent();

            $table->index(['evento_tipo', 'creado_en']);
            $table->index(['entidad_tipo', 'entidad_id', 'creado_en']);
        });

        Schema::create('config_sistema', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 80)->unique(); // modo_global, stale_min, retention_days...
            $table->json('valor');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('config_sistema');
        Schema::dropIfExists('auditoria_eventos');
        Schema::dropIfExists('alertas');
        Schema::dropIfExists('reglas_alerta');
        Schema::dropIfExists('ejecuciones_programacion');
        Schema::dropIfExists('programaciones');
        Schema::dropIfExists('estado_reglas');
        Schema::dropIfExists('reglas_automaticas');
    }
};