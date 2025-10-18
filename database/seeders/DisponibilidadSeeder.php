<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use App\Models\User;
use App\Models\BloqueDisponibilidad;
use App\Models\ExcepcionDisponibilidad;

class DisponibilidadSeeder extends Seeder
{
    public function run(): void
    {
        // Buscar kinesióloga existente o crearla si no está
        $kine = User::where('email', 'silviaduarte@gmail.com')->first();

        if (!$kine) {
            $kine = User::create([
                'name' => 'Silvia Duarte',
                'email' => 'silviaduarte@gmail.com',
                'password' => bcrypt('12345678'),
            ]);
        }

        // Asignar el rol
        $kine->assignRole('Kinesiologa');

        // Crear bloques de disponibilidad semanales
        $bloques = [
            ['dia_semana' => 1, 'hora_desde' => '08:00:00', 'hora_hasta' => '12:00:00'],
            ['dia_semana' => 1, 'hora_desde' => '16:00:00', 'hora_hasta' => '20:00:00'],
            ['dia_semana' => 3, 'hora_desde' => '08:00:00', 'hora_hasta' => '12:00:00'],
            ['dia_semana' => 3, 'hora_desde' => '16:00:00', 'hora_hasta' => '20:00:00'],
            ['dia_semana' => 5, 'hora_desde' => '08:00:00', 'hora_hasta' => '12:00:00'],
        ];

        foreach ($bloques as $b) {
            BloqueDisponibilidad::updateOrCreate([
                'profesional_id' => $kine->id,
                'dia_semana'     => $b['dia_semana'],
                'hora_desde'     => $b['hora_desde'],
                'hora_hasta'     => $b['hora_hasta'],
            ], [
                'activo'          => true,
                'duracion_minutos' => 45,
            ]);
        }

        // Crear excepciones de ejemplo
        $excepciones = [
            ['fecha' => Carbon::now()->addDays(2), 'motivo' => 'Turno médico', 'bloqueado' => true],
            ['fecha' => Carbon::now()->addDays(5), 'motivo' => 'Congreso de fisioterapia', 'bloqueado' => true],
            ['fecha' => Carbon::now()->addDays(10), 'motivo' => 'Licencia personal', 'bloqueado' => false, 'hora_desde' => '08:00:00', 'hora_hasta' => '10:00:00'],
        ];

        foreach ($excepciones as $e) {
            ExcepcionDisponibilidad::updateOrCreate([
                'profesional_id' => $kine->id,
                'fecha'          => $e['fecha'],
            ], $e);
        }
    }
}
