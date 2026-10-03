<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('camaras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('modulo_id')->constrained('modulos')->cascadeOnDelete();
            $table->string('codigo', 40);
            $table->string('nombre', 120);
            $table->string('stream_key', 80)->unique();
            $table->string('tipo', 30)->default('ip');
            $table->boolean('habilitada')->default(true);
            $table->timestamp('ultimo_contacto')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['modulo_id', 'codigo']);
            $table->index(['modulo_id', 'habilitada']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('camaras');
    }
};
