<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            // Explicit DEFAULT suppresses the legacy implicit ON UPDATE behavior.
            // Keep existing values and indexes; do not delete or rewrite history.
            DB::statement('ALTER TABLE ejecuciones_programacion MODIFY inicio_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');
        }
    }

    public function down(): void
    {
        // Do not restore the defect that changes a scheduled start when finishing.
    }
};
