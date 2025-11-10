<?php

namespace Tests\Feature\Filament\Kinesiologa;

use App\Filament\Kinesiologa\Pages\MiAgendaSemanal;
use App\Models\ExcepcionDisponibilidad;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Support\ActsAsKine;
use Carbon\Carbon;
use Spatie\Permission\Models\Role;

class ExcepcionesDisponibilidadTest extends TestCase
{
    use RefreshDatabase, ActsAsKine;

    protected function setUp(): void
    {
        parent::setUp();

        // (Opcional) Por si tus reglas usan estos valores en algún lugar
        Config::set('turnos.confirm_min_minutes', 180);
        Config::set('turnos.cancel_min_minutes', 1440);

        // Crear roles si usás Spatie
        if (class_exists(Role::class)) {
            Role::findOrCreate('Kinesiologa');
            Role::findOrCreate('Administrador');
        }
    }

    protected function actingAsKine(): User
    {
        $kine = User::factory()->create();
        if (method_exists($kine, 'assignRole')) {
            $kine->assignRole('Kinesiologa');
        }
        $this->actingAsKine();
        return $kine;
    }

    #[Test]
    public function no_permite_dos_full_day_el_mismo_dia(): void
    {
        $kine = $this->actingAsKine();

        $fecha = '2025-11-10';

        // Ya existe un full-day para esa fecha
        ExcepcionDisponibilidad::create([
            'profesional_id' => $kine->id,
            'fecha'          => $fecha,
            'bloqueado'      => true,
            'hora_desde'     => null,
            'hora_hasta'     => null,
            'motivo'         => 'Congreso',
        ]);

        // Intento cargar otro full-day en la misma fecha → debe rechazarse (quedarse en 1)
        Livewire::test(MiAgendaSemanal::class)
            ->set('nuevaExcepcion.fecha', $fecha)
            ->set('nuevaExcepcion.bloqueado', true)
            ->set('nuevaExcepcion.hora_desde', null)
            ->set('nuevaExcepcion.hora_hasta', null)
            ->call('agregarExcepcion');

        $this->assertDatabaseCount('excepciones_disponibilidad', 1);
        $this->assertDatabaseHas('excepciones_disponibilidad', [
            'profesional_id' => $kine->id,
            'fecha'          => $fecha,
            'bloqueado'      => 1,
            'hora_desde'     => null,
            'hora_hasta'     => null,
        ]);
    }

    #[Test]
    public function parciales_no_solapadas_se_permiten_y_solapadas_se_rechazan(): void
    {
        $kine  = $this->actingAsKine();
        $fecha = '2025-11-11';

        // 1) Cargo una parcial 08:00–10:00 (OK)
        Livewire::test(MiAgendaSemanal::class)
            ->set('nuevaExcepcion.fecha', $fecha)
            ->set('nuevaExcepcion.bloqueado', false)
            ->set('nuevaExcepcion.hora_desde', '08:00')
            ->set('nuevaExcepcion.hora_hasta', '10:00')
            ->call('agregarExcepcion');

        $this->assertDatabaseHas('excepciones_disponibilidad', [
            'profesional_id' => $kine->id,
            'fecha'          => $fecha,
            'bloqueado'      => 0,
            'hora_desde'     => '08:00:00',
            'hora_hasta'     => '10:00:00',
        ]);

        // 2) Intento otra parcial solapada 09:00–11:00 (RECHAZADA)
        Livewire::test(MiAgendaSemanal::class)
            ->set('nuevaExcepcion.fecha', $fecha)
            ->set('nuevaExcepcion.bloqueado', false)
            ->set('nuevaExcepcion.hora_desde', '09:00')
            ->set('nuevaExcepcion.hora_hasta', '11:00')
            ->call('agregarExcepcion');

        // Debe seguir habiendo solo 1 registro
        $this->assertEquals(
            1,
            ExcepcionDisponibilidad::where('profesional_id', $kine->id)->whereDate('fecha', $fecha)->count()
        );

        // 3) Una parcial NO solapada 11:00–12:00 (OK)
        Livewire::test(MiAgendaSemanal::class)
            ->set('nuevaExcepcion.fecha', $fecha)
            ->set('nuevaExcepcion.bloqueado', false)
            ->set('nuevaExcepcion.hora_desde', '11:00')
            ->set('nuevaExcepcion.hora_hasta', '12:00')
            ->call('agregarExcepcion');

        $this->assertEquals(
            2,
            ExcepcionDisponibilidad::where('profesional_id', $kine->id)->whereDate('fecha', $fecha)->count()
        );
    }

    #[Test]
    public function si_existe_full_day_rechaza_cualquier_parcial_en_esa_fecha(): void
    {
        $kine  = $this->actingAsKine();
        $fecha = '2025-11-12';

        // Full-day existente
        ExcepcionDisponibilidad::create([
            'profesional_id' => $kine->id,
            'fecha'          => $fecha,
            'bloqueado'      => true,
            'hora_desde'     => null,
            'hora_hasta'     => null,
            'motivo'         => null,
        ]);

        // Intentar una parcial → debe rechazarse
        Livewire::test(MiAgendaSemanal::class)
            ->set('nuevaExcepcion.fecha', $fecha)
            ->set('nuevaExcepcion.bloqueado', false)
            ->set('nuevaExcepcion.hora_desde', '09:00')
            ->set('nuevaExcepcion.hora_hasta', '10:00')
            ->call('agregarExcepcion');

        // Sigue habiendo solo 1 (el full-day)
        $this->assertEquals(
            1,
            ExcepcionDisponibilidad::where('profesional_id', $kine->id)->whereDate('fecha', $fecha)->count()
        );
    }
}
