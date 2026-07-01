<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('lecturas', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('sensor_id')->constrained('sensores')->cascadeOnDelete();

            $table->decimal('valor', 10, 3);
            $table->timestamp('medido_en');                // hora de medición (ESP32 ideal)
            $table->timestamp('recibido_en')->useCurrent(); // llegada al server

            $table->enum('calidad', ['ok', 'dudoso', 'error'])->default('ok');
            $table->json('raw')->nullable();

            $table->timestamps();

            $table->index(['sensor_id', 'medido_en']);
            $table->index('medido_en');
        });

        Schema::create('comandos_iot', function (Blueprint $table) {
            $table->id();

            $table->foreignId('modulo_id')->constrained('modulos')->cascadeOnDelete();
            $table->foreignId('actuador_id')->nullable()->constrained('actuadores')->nullOnDelete();

            // alimentar | agua | set_estado | mover | custom
            $table->string('tipo', 40);
            $table->json('payload');

            // pendiente | enviado | confirmado | fallido | expirado
            $table->enum('estado', ['pendiente', 'enviado', 'confirmado', 'fallido', 'expirado'])->default('pendiente');

            $table->string('nonce', 64)->unique();
            $table->unsignedInteger('intentos')->default(0);

            $table->timestamp('enviado_en')->nullable();
            $table->timestamp('confirmado_en')->nullable();
            $table->timestamp('expira_en')->nullable();

            $table->string('ultimo_error', 255)->nullable();

            $table->timestamps();

            $table->index(['modulo_id', 'estado', 'created_at']);
            $table->index(['estado', 'created_at']);
        });

        Schema::create('actuaciones', function (Blueprint $table) {
            $table->id();

            $table->foreignId('modulo_id')->constrained('modulos')->cascadeOnDelete();
            $table->foreignId('actuador_id')->constrained('actuadores')->cascadeOnDelete();

            // manual | auto | programacion | regla | sistema
            $table->enum('origen', ['manual', 'auto', 'programacion', 'regla', 'sistema'])->default('sistema');

            $table->json('estado_anterior')->nullable();
            $table->json('estado_nuevo');
            $table->json('motivo')->nullable(); // {"sensor":"S_TEMP","valor":12.3,"regla_id":5}

            $table->timestamp('ejecutado_en')->useCurrent();
            $table->timestamps();

            $table->index(['actuador_id', 'ejecutado_en']);
            $table->index(['modulo_id', 'ejecutado_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('actuaciones');
        Schema::dropIfExists('comandos_iot');
        Schema::dropIfExists('lecturas');
    }
};