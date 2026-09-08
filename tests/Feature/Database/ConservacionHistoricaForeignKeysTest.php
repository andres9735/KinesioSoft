<?php

namespace Tests\Feature\Database;

use App\Models\ColegioEmisor;
use App\Models\EspecialidadKinesiologia;
use App\Models\Jurisdiccion;
use App\Models\Kinesiologa;
use App\Models\MatriculaProfesionalKinesiologia;
use App\Models\Paciente;
use App\Models\TituloProfesional;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ConservacionHistoricaForeignKeysTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_prevents_deleting_user_with_paciente_profile(): void
    {
        $user = User::factory()->create();

        $paciente = Paciente::create([
            'user_id' => $user->id,
            'activo' => true,
        ]);

        $this->assertDeleteIsRestricted(
            table: 'users',
            id: $user->id
        );

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
        ]);

        $this->assertDatabaseHas('pacientes', [
            'id' => $paciente->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_database_prevents_deleting_user_with_kinesiologa_profile(): void
    {
        $titulo = $this->tituloProfesional();

        $user = User::factory()->create();

        $kinesiologa = Kinesiologa::create([
            'user_id' => $user->id,
            'titulo_profesional_id' => $titulo->id,
            'consultorio_id' => null,
        ]);

        $this->assertDeleteIsRestricted(
            table: 'users',
            id: $user->id
        );

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
        ]);

        $this->assertDatabaseHas('kinesiologas', [
            'id' => $kinesiologa->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_database_prevents_deleting_kinesiologa_with_matricula(): void
    {
        $titulo = $this->tituloProfesional();
        $jurisdiccion = $this->jurisdiccion();
        $colegio = $this->colegioEmisor($jurisdiccion);

        $user = User::factory()->create();

        $kinesiologa = Kinesiologa::create([
            'user_id' => $user->id,
            'titulo_profesional_id' => $titulo->id,
            'consultorio_id' => null,
        ]);

        $matricula = MatriculaProfesionalKinesiologia::create([
            'kinesiologa_id' => $kinesiologa->id,
            'nro_matricula_profesional' => 'MP-900001',
            'jurisdiccion_id' => $jurisdiccion->id,
            'colegio_emisor_id' => $colegio->id,
            'activa' => true,
        ]);

        $this->assertDeleteIsRestricted(
            table: 'kinesiologas',
            id: $kinesiologa->id
        );

        $this->assertDatabaseHas('kinesiologas', [
            'id' => $kinesiologa->id,
        ]);

        $this->assertDatabaseHas(
            'matriculas_profesionales_kinesiologia',
            [
                'id' => $matricula->id,
                'kinesiologa_id' => $kinesiologa->id,
            ]
        );
    }

    public function test_database_prevents_deleting_kinesiologa_with_specialty_history(): void
    {
        $titulo = $this->tituloProfesional();
        $especialidad = $this->especialidad();

        $user = User::factory()->create();

        $kinesiologa = Kinesiologa::create([
            'user_id' => $user->id,
            'titulo_profesional_id' => $titulo->id,
            'consultorio_id' => null,
        ]);

        $kinesiologa
            ->especialidades()
            ->sync([$especialidad->id]);

        $this->assertDeleteIsRestricted(
            table: 'kinesiologas',
            id: $kinesiologa->id
        );

        $this->assertDatabaseHas('kinesiologas', [
            'id' => $kinesiologa->id,
        ]);

        $this->assertDatabaseHas('kinesiologa_especialidad', [
            'kinesiologa_id' => $kinesiologa->id,
            'especialidad_id' => $especialidad->id,
        ]);
    }

    private function assertDeleteIsRestricted(
        string $table,
        int $id
    ): void {
        try {
            DB::table($table)
                ->where('id', $id)
                ->delete();

            $this->fail(
                "Se esperaba que la base de datos impidiera eliminar {$table} con relaciones históricas."
            );
        } catch (QueryException) {
            $this->assertDatabaseHas($table, [
                'id' => $id,
            ]);
        }
    }

    private function tituloProfesional(): TituloProfesional
    {
        return TituloProfesional::create([
            'nombre' => 'Licenciado en Kinesiología',
            'slug' => 'licenciado-en-kinesiologia-conservacion',
            'activo' => true,
        ]);
    }

    private function jurisdiccion(): Jurisdiccion
    {
        return Jurisdiccion::create([
            'nombre' => 'Misiones',
            'slug' => 'misiones-conservacion',
            'activo' => true,
        ]);
    }

    private function colegioEmisor(
        Jurisdiccion $jurisdiccion
    ): ColegioEmisor {
        return ColegioEmisor::create([
            'nombre' => 'Colegio de Kinesiólogos de Conservación',
            'sigla' => 'CKHC',
            'jurisdiccion_id' => $jurisdiccion->id,
            'activo' => true,
        ]);
    }

    private function especialidad(): EspecialidadKinesiologia
    {
        return EspecialidadKinesiologia::create([
            'nombre' => 'Kinesiología Histórica',
            'slug' => 'kinesiologia-historica-conservacion',
            'activo' => true,
        ]);
    }
}
