<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('comandos_iot', function (Blueprint $table) {
            if (!Schema::hasColumn('comandos_iot', 'ejecutar_en')) {
                $table->timestamp('ejecutar_en')->nullable()->after('intentos');
                $table->index(['modulo_id', 'estado', 'ejecutar_en'], 'comandos_iot_mod_estado_ejecutar_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('comandos_iot', function (Blueprint $table) {
            if (Schema::hasColumn('comandos_iot', 'ejecutar_en')) {
                $table->dropIndex('comandos_iot_mod_estado_ejecutar_idx');
                $table->dropColumn('ejecutar_en');
            }
        });
    }
};
