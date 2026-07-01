<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
        $table->string('rol', 20)->default('lector')->after('password');    
        //$table->string('rol', 20)->default('admin')->after('password');
            $table->index('rol');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['rol']);
            $table->dropColumn('rol');
        });
    }
};