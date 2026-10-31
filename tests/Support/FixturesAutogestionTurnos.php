<?php

namespace Tests\Support;

use App\Enums\RoleEnum;
use App\Models\DeclaracionSaludPaciente;
use App\Models\Kinesiologa;
use App\Models\Localidad;
use App\Models\Paciente;
use App\Models\Provincia;
use App\Models\TituloProfesional;
use App\Models\Turno;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/** Datos sintéticos compartidos; nunca usa seeders ni datos clínicos existentes. */
final class FixturesAutogestionTurnos
{
    private array $ids = [];

    private function recordar(Model $modelo): mixed
    {
        if ($modelo->wasRecentlyCreated) {
            $this->ids[$modelo->getTable()][] = $modelo->getKey();
        }

        return $modelo;
    }

    public function paciente(): Paciente
    {
        $user = $this->recordar(User::factory()->create([
            'dni' => fake()->unique()->numerify('########'), 'fecha_nacimiento' => '1990-01-01',
            'sexo' => 'femenino', 'telefono' => '3764000000', 'activo' => true,
        ]));
        $rol = $this->recordar(Role::firstOrCreate(['name' => RoleEnum::PACIENTE->value, 'guard_name' => 'web']));
        $user->assignRole($rol);
        $provincia = $this->recordar(Provincia::firstOrCreate(['georef_id' => '54'], ['nombre' => 'Misiones']));
        $localidad = $this->recordar(Localidad::firstOrCreate(['georef_id' => '54098010'], [
            'provincia_id' => $provincia->id, 'nombre' => 'Posadas', 'departamento_nombre' => 'Capital',
        ]));
        $paciente = $this->recordar(Paciente::create([
            'user_id' => $user->id, 'activo' => true, 'localidad_id' => $localidad->id,
            'calle' => 'Fixture', 'altura_domicilio' => '123', 'contacto_emergencia_nombre' => 'Fixture',
            'contacto_emergencia_telefono' => '3764000001',
        ]));
        $this->recordar(DeclaracionSaludPaciente::create([
            'paciente_id' => $paciente->id, 'objetivo_salud' => 'Fixture sintético',
            'peso_kg' => 70, 'altura_cm' => 170, 'ocupacion' => 'Fixture',
            'nivel_actividad_fisica' => 'MODERADO', 'detalle_actividad_fisica' => 'Fixture',
            'estado' => DeclaracionSaludPaciente::ESTADO_PENDIENTE_REVISION,
        ]));

        return $paciente;
    }

    public function profesional(): Kinesiologa
    {
        $user = $this->recordar(User::factory()->create());
        $titulo = $this->recordar(TituloProfesional::firstOrCreate(['slug' => 'uc06-concurrencia-fixture'], ['nombre' => 'Fixture', 'activo' => true]));

        return $this->recordar(Kinesiologa::create(['user_id' => $user->id, 'titulo_profesional_id' => $titulo->id]));
    }

    public function turno(Paciente $paciente, Kinesiologa $profesional, array $datos = []): Turno
    {
        $ahora = CarbonImmutable::now(config('app.timezone'));

        return $this->recordar(Turno::factory()->for($paciente)->for($profesional)->create($datos + [
            'tipo' => 'PRIMERA_CONSULTA', 'estado' => 'PENDIENTE',
            'inicio_at' => $ahora->addDay(), 'fin_at' => $ahora->addDay()->addMinutes(45),
            'motivo_solicitud' => 'Fixture sintético privado',
        ]))->fresh();
    }

    /** Sólo los IDs creados por esta instancia; no borra tablas ni reinicializa el esquema. */
    public function limpiar(): void
    {
        DB::transaction(function (): void {
            $turnos = $this->ids['turnos'] ?? [];
            DB::table('intenciones_evaluacion_cupo')->whereIn('turno_id', $turnos)->delete();
            DB::table('auditorias')->where('auditable_type', (new Turno)->getMorphClass())->whereIn('auditable_id', $turnos)->delete();
            foreach (['model_has_roles', 'model_has_permissions'] as $tabla) {
                DB::table($tabla)->where('model_type', (new User)->getMorphClass())->whereIn('model_id', $this->ids['users'] ?? [])->delete();
            }
            foreach (['turnos', 'declaraciones_salud_paciente', 'pacientes', 'kinesiologas', 'users', 'roles', 'localidades', 'provincias', 'titulos_profesionales'] as $tabla) {
                DB::table($tabla)->whereIn('id', $this->ids[$tabla] ?? [])->delete();
            }
        }, attempts: 1);
    }
}
