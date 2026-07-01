<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('modulos_credenciales', 'api_key_encrypted')) {
            Schema::table('modulos_credenciales', function (Blueprint $table) {
                $table->text('api_key_encrypted')->nullable()->after('api_key_hash');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('modulos_credenciales', 'api_key_encrypted')) {
            Schema::table('modulos_credenciales', function (Blueprint $table) {
                $table->dropColumn('api_key_encrypted');
            });
        }
    }
};
