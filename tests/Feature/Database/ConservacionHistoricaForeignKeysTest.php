<?php

namespace Tests\Feature\Database;

use App\Models\CategoriaClinica;
use App\Models\ColegioEmisor;
use App\Models\EjercicioDomiciliario;
use App\Models\EspecialidadKinesiologia;
use App\Models\Jurisdiccion;
use App\Models\Kinesiologa;
use App\Models\MatriculaProfesionalKinesiologia;
use App\Models\Paciente;
use App\Models\RecursoEjercicio;
use App\Models\TipoEquipoTerapeutico;
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

    public function test_database_prevents_deleting_clinical_category_with_child(): void
    {
        $padre = $this->categoriaClinica('CAT-PADRE');
        $hija = $this->categoriaClinica('CAT-HIJA', 'ANATOMICA', $padre->id);

        $this->assertDeleteIsRestricted('categorias_clinicas', $padre->id);

        $this->assertDatabaseHas('categorias_clinicas', [
            'id' => $padre->id,
            'codigo' => 'CAT-PADRE',
        ]);
        $this->assertDatabaseHas('categorias_clinicas', [
            'id' => $hija->id,
            'categoria_padre_id' => $padre->id,
        ]);
    }

    public function test_database_prevents_deleting_exercise_with_category_pivot(): void
    {
        $ejercicio = $this->ejercicioDomiciliario();
        $categoria = $this->categoriaClinica('CAT-EJERCICIO');
        $ejercicio->categorias()->attach($categoria);

        $this->assertDeleteIsRestricted('ejercicios_domiciliarios', $ejercicio->id);

        $this->assertDatabaseHas('ejercicios_domiciliarios', [
            'id' => $ejercicio->id,
            'codigo' => 'EJ-CONSERVACION',
        ]);
        $this->assertDatabaseHas('categorias_clinicas', [
            'id' => $categoria->id,
            'codigo' => 'CAT-EJERCICIO',
        ]);
        $this->assertDatabaseHas('ejercicio_categoria', [
            'ejercicio_id' => $ejercicio->id,
            'categoria_id' => $categoria->id,
        ]);
    }

    public function test_database_prevents_deleting_clinical_category_with_exercise_pivot(): void
    {
        $ejercicio = $this->ejercicioDomiciliario();
        $categoria = $this->categoriaClinica('CAT-PIVOTE');
        $ejercicio->categorias()->attach($categoria);

        $this->assertDeleteIsRestricted('categorias_clinicas', $categoria->id);

        $this->assertDatabaseHas('categorias_clinicas', [
            'id' => $categoria->id,
            'codigo' => 'CAT-PIVOTE',
        ]);
        $this->assertDatabaseHas('ejercicios_domiciliarios', [
            'id' => $ejercicio->id,
            'codigo' => 'EJ-CONSERVACION',
        ]);
        $this->assertDatabaseHas('ejercicio_categoria', [
            'ejercicio_id' => $ejercicio->id,
            'categoria_id' => $categoria->id,
        ]);
    }

    public function test_database_prevents_deleting_exercise_with_resource(): void
    {
        $ejercicio = $this->ejercicioDomiciliario();
        $recurso = RecursoEjercicio::create([
            'ejercicio_id' => $ejercicio->id,
            'tipo' => 'VIDEO',
            'origen' => 'EXTERNO',
            'url_externa' => 'https://example.com/ejercicio',
            'titulo' => 'Video de ejercicio',
            'orden' => 1,
            'activo' => true,
        ]);

        $this->assertDeleteIsRestricted('ejercicios_domiciliarios', $ejercicio->id);

        $this->assertDatabaseHas('ejercicios_domiciliarios', [
            'id' => $ejercicio->id,
            'codigo' => 'EJ-CONSERVACION',
        ]);
        $this->assertDatabaseHas('recursos_ejercicio', [
            'id' => $recurso->id,
            'ejercicio_id' => $ejercicio->id,
        ]);
    }

    public function test_database_prevents_deleting_clinical_category_with_equipment_type(): void
    {
        $categoria = $this->categoriaClinica('CAT-EQUIPO', 'EQUIPAMIENTO');
        $tipoEquipo = TipoEquipoTerapeutico::create([
            'codigo' => 'EQUIPO-CONSERVACION',
            'nombre' => 'Equipo de prueba',
            'categoria_clinica_id' => $categoria->id,
            'activo' => true,
        ]);

        $this->assertDeleteIsRestricted('categorias_clinicas', $categoria->id);

        $this->assertDatabaseHas('categorias_clinicas', [
            'id' => $categoria->id,
            'codigo' => 'CAT-EQUIPO',
        ]);
        $this->assertDatabaseHas('tipos_equipo_terapeutico', [
            'id' => $tipoEquipo->id,
            'categoria_clinica_id' => $categoria->id,
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

    private function categoriaClinica(
        string $codigo,
        string $tipo = 'ANATOMICA',
        ?int $padreId = null
    ): CategoriaClinica {
        return CategoriaClinica::create([
            'categoria_padre_id' => $padreId,
            'codigo' => $codigo,
            'nombre' => $codigo,
            'tipo' => $tipo,
            'activo' => true,
        ]);
    }

    private function ejercicioDomiciliario(): EjercicioDomiciliario
    {
        return EjercicioDomiciliario::create([
            'codigo' => 'EJ-CONSERVACION',
            'nombre' => 'Ejercicio de prueba',
            'descripcion' => 'Descripción mínima para conservar la referencia.',
            'dificultad_base' => 'BAJA',
            'activo' => true,
        ]);
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
