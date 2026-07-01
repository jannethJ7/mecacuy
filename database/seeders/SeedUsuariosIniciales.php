<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SeedUsuariosIniciales extends Seeder
{
    public function run(): void
    {
        $ahora = now();

        $usuarios = [
            [
                'name' => 'Administrador',
                'email' => 'admin@mecacuy.com',
                'password' => 'Admin12345',
                'rol' => 'admin',
            ],
            [
                'name' => 'Operador',
                'email' => 'operador@mecacuy.com',
                'password' => 'Operador12345',
                'rol' => 'operador',
            ],
            [
                'name' => 'Lector',
                'email' => 'lector@mecacuy.com',
                'password' => 'Lector12345',
                'rol' => 'lector',
            ],
        ];

        foreach ($usuarios as $u) {
            DB::table('users')->updateOrInsert(
                ['email' => $u['email']],
                [
                    'name' => $u['name'],
                    'password' => Hash::make($u['password']),
                    'rol' => $u['rol'],
                    'email_verified_at' => $ahora,   // para que no te pida verificar email
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ]
            );
        }

        $this->command?->info("✅ Usuarios iniciales creados/actualizados:");
        $this->command?->line("admin@mecacuy.com / Admin12345");
        $this->command?->line("operador@mecacuy.com / Operador12345");
        $this->command?->line("lector@mecacuy.com / Lector12345");
    }
}