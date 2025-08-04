<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Crear los roles en la base de datos
        Role::factory()->create([
            'nombre_rol' => 'Administrador',
            'descripcion' => 'Administrador del sistema',
        ]);
        Role::factory()->create([
            'nombre_rol' => 'Nutricionista',
            'descripcion' => 'Encargado de la nutrición',
        ]);
        Role::factory()->create([
            'nombre_rol' => 'Paciente',
            'descripcion' => 'Encargado de la atención al paciente',
        ]);

        // Crear un usuario administrador
        User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@gmail.com',
            'password' => bcrypt('admin123'),
            'roles_id' => 1,
        ]);
    }
}