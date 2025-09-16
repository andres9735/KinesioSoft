<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Asegurar roles base (no toca tu Admin existente)
        $pacienteRole = Role::firstOrCreate(['name' => 'Paciente']);
        $kineRole     = Role::firstOrCreate(['name' => 'Kinesiologa']);

        // Faker en español (Argentina)
        $faker = \Faker\Factory::create('es_AR');

        // Especialidades ejemplo para Kinesiología
        $especialidades = [
            'Kinesiología Deportiva',
            'Neurorehabilitación',
            'Traumatológica',
            'Respiratoria',
            'Pediátrica',
            'Geriátrica',
            'Suelo pélvico',
            'Cardiorrespiratoria',
        ];

        // Generamos 10 usuarios de prueba
        for ($i = 1; $i <= 10; $i++) {
            $esKine   = $i % 2 === 0;            // pares: Kinesiologa, impares: Paciente
            $nombre   = $faker->firstName();
            $apellido = $faker->lastName();

            $user = User::create([
                'name'          => "{$nombre} {$apellido}",
                'email'         => $faker->unique()->safeEmail(),
                'password'      => Hash::make('password123'), // contraseña de prueba
                'phone'         => $faker->numerify('11########'),               // 11 + 8 dígitos
                'dni'           => $faker->unique()->numerify('########'),        // 8 dígitos
                'address'       => $faker->streetName().' '.$faker->buildingNumber().' - '.$faker->city(),
                'specialty'     => $esKine ? $faker->randomElement($especialidades) : null,
                'is_active'     => $faker->boolean(85), // 85% activos
                'last_login_at' => now()->subDays(rand(0, 30))->setTime(rand(8, 20), rand(0, 59)),
                'remember_token'=> Str::random(10),
            ]);

            // Asignar rol
            $user->syncRoles([$esKine ? $kineRole : $pacienteRole]);
        }
    }
}
