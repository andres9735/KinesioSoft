<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Paciente; // 👈 NUEVO
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $pacienteRole = Role::firstOrCreate(['name' => 'Paciente']);
        $kineRole     = Role::firstOrCreate(['name' => 'Kinesiologa']);

        $faker = \Faker\Factory::create('es_AR');

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

        for ($i = 1; $i <= 10; $i++) {
            $esKine   = $i % 2 === 0;
            $nombre   = $faker->firstName();
            $apellido = $faker->lastName();

            $user = User::create([
                'name'          => "{$nombre} {$apellido}",
                'email'         => $faker->unique()->safeEmail(),
                'password'      => Hash::make('password123'),
                'phone'         => $faker->numerify('11########'),
                'dni'           => $faker->unique()->numerify('########'),
                'address'       => $faker->streetName() . ' ' . $faker->buildingNumber() . ' - ' . $faker->city(),
                'specialty'     => $esKine ? $faker->randomElement($especialidades) : null,
                'is_active'     => $faker->boolean(85),
                'last_login_at' => now()->subDays(rand(0, 30))->setTime(rand(8, 20), rand(0, 59)),
                'remember_token' => Str::random(10),
            ]);

            $user->syncRoles([$esKine ? $kineRole : $pacienteRole]);
            \App\Services\PacienteService::ensureProfile($user);

            // 👇 Asegurar perfil clínico si es Paciente
            if ($user->hasRole('Paciente')) {
                Paciente::firstOrCreate(
                    ['user_id' => $user->id],
                    ['nombre'  => $user->name]
                );
            }
        }
    }
}
