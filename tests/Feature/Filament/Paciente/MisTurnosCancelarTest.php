<?php

namespace Tests\Feature\Filament\Paciente;

use App\Filament\Paciente\Pages\MisTurnos;
use App\Models\Turno;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MisTurnosCancelarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Config de lead times
        Config::set('turnos.confirm_min_minutes', 180);   // 3 h
        Config::set('turnos.cancel_min_minutes', 1440);   // 24 h

        // Si usás Spatie-Permission: asegurar roles y limpiar cache
        if (class_exists(Role::class)) {
            Role::firstOrCreate(['name' => 'Paciente']);
            Role::firstOrCreate(['name' => 'Administrador']);
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    /** @test */
    public function al_cancelar_faltando_menos_de_deadline_pero_en_futuro_se_marca_como_cancelado_tarde(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-11-03 10:00:00')); // Lunes 10:00

        /** @var User $paciente */
        $paciente = User::factory()->createOne();
        if (method_exists($paciente, 'assignRole')) {
            $paciente->assignRole('Paciente');
        }
        $this->actingAs($paciente); // 👈 un solo usuario, no Collection

        /** @var User $profesional */
        $profesional = User::factory()->createOne();

        // Turno en 60 minutos (futuro), menor al deadline 24h
        $inicio = now()->addMinutes(60); // 11:00
        /** @var Turno $turno */
        $turno = Turno::create([
            'profesional_id' => $profesional->id,
            'paciente_id'    => $paciente->id,
            'id_consultorio' => null,
            'fecha'          => $inicio->toDateString(),
            'hora_desde'     => $inicio->format('H:i'),
            'hora_hasta'     => $inicio->copy()->addMinutes(45)->format('H:i'),
            'estado'         => Turno::ESTADO_PENDIENTE,
            'motivo'         => null,
        ]);

        Livewire::test(MisTurnos::class)
            ->callTableAction('cancelar', $turno, ['motivo' => 'No puedo asistir']);

        $turno->refresh();
        $this->assertEquals(Turno::ESTADO_CANCELADO_TARDE, $turno->estado);
        $this->assertSame('No puedo asistir', $turno->motivo);
    }

    /** @test */
    public function al_cancelar_faltando_mas_que_el_deadline_queda_como_cancelado_normal(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-11-03 10:00:00'));

        /** @var User $paciente */
        $paciente = User::factory()->createOne();
        if (method_exists($paciente, 'assignRole')) {
            $paciente->assignRole('Paciente');
        }
        $this->actingAs($paciente);

        /** @var User $profesional */
        $profesional = User::factory()->createOne();

        // Turno dentro de 2 días (> 1440 min)
        $inicio = now()->addDays(2)->setTime(9, 0);
        /** @var Turno $turno */
        $turno = Turno::create([
            'profesional_id' => $profesional->id,
            'paciente_id'    => $paciente->id,
            'id_consultorio' => null,
            'fecha'          => $inicio->toDateString(),
            'hora_desde'     => $inicio->format('H:i'),
            'hora_hasta'     => $inicio->copy()->addMinutes(45)->format('H:i'),
            'estado'         => Turno::ESTADO_PENDIENTE,
            'motivo'         => null,
        ]);

        Livewire::test(MisTurnos::class)
            ->callTableAction('cancelar', $turno, ['motivo' => 'Cambio de agenda']);

        $turno->refresh();
        $this->assertEquals(Turno::ESTADO_CANCELADO, $turno->estado);
        $this->assertSame('Cambio de agenda', $turno->motivo);
    }
}
