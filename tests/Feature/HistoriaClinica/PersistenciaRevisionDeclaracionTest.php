<?php

namespace Tests\Feature\HistoriaClinica;

use App\Enums\DictamenRevisionDeclaracion;
use App\Models\ConfirmacionRevisionDeclaracion;
use App\Models\DeclaracionSaludPaciente;
use App\Models\HistoriaClinica;
use App\Models\IncorporacionDeclaracionSalud;
use App\Models\Kinesiologa;
use App\Models\Paciente;
use App\Models\RevisionDeclaracionSalud;
use App\Models\Turno;
use App\Models\VersionDatosClinicosBase;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CreaPerfilesUc07;
use Tests\TestCase;

class PersistenciaRevisionDeclaracionTest extends TestCase
{
    use CreaPerfilesUc07;
    use RefreshDatabase;

    private Kinesiologa $profesional;

    private Paciente $paciente;

    private DeclaracionSaludPaciente $declaracion;

    private HistoriaClinica $historia;

    private Turno $turno;

    protected function setUp(): void
    {
        parent::setUp();
        $this->profesional = $this->profesionalUc07();
        $this->paciente = $this->pacienteUc07();
        $this->declaracion = $this->paciente->declaracionesSalud()->sole();
        $this->historia = HistoriaClinica::create(['paciente_id' => $this->paciente->id, 'nro_historia_clinica' => 'HC-CONTRATO', 'activa' => true]);
        $this->turno = Turno::factory()->for($this->paciente)->for($this->profesional)->create();
    }

    public function test_relaciona_original_dictamen_confirmacion_autoria_e_incorporacion(): void
    {
        $base = $this->version(1, ['clave_apertura' => (string) Str::uuid(), 'origen' => 'CARGA_PROFESIONAL_DIRECTA', 'altura_cm' => 170]);
        $confirmacion = $this->confirmacion(['version_base_id' => $base->id]);
        $revision = $this->revision($confirmacion);
        $version = $this->version(2, ['altura_cm' => '170.25']);
        $incorporacion = $this->incorporacion($confirmacion, $version);
        $this->assertSame($this->declaracion->id, $revision->declaracion->id);
        $this->assertSame($confirmacion->id, $revision->confirmacion->id);
        $this->assertSame($this->profesional->id, $revision->kinesiologa->id);
        $this->assertSame($base->id, $confirmacion->versionBase->id);
        $this->assertSame($this->profesional->user_id, $confirmacion->autora->id);
        $this->assertSame($this->turno->id, $confirmacion->turnoOrigen->id);
        $this->assertSame($version->id, $confirmacion->incorporacion->version->id);
        $this->assertSame($confirmacion->id, $version->incorporacion->confirmacion->id);
        $this->assertSame($this->historia->id, $incorporacion->historiaClinica->id);
        $this->assertSame($version->id, $this->declaracion->incorporacion->version->id);
        $this->assertSame(1, $this->declaracion->revisiones()->count());
        $this->assertSame($confirmacion->id, $this->declaracion->confirmacion->id);
        $this->assertSame('170.00', $base->fresh()->altura_cm);
        $this->assertSame('170.25', $version->fresh()->altura_cm);
        $this->assertNull($version->clave_apertura);
        $this->assertSame(['valor' => 70], $revision->datos_originales);
        $this->assertSame(DictamenRevisionDeclaracion::VALIDAR, $revision->dictamen);
    }

    public function test_nullable_clave_admite_varias_versiones_posteriores_y_preserva_unicidad_de_apertura(): void
    {
        $clave = (string) Str::uuid();
        $this->version(1, ['clave_apertura' => $clave]);
        $this->version(2);
        $this->version(3);
        $this->assertDatabaseCount('versiones_datos_clinicos_base', 3);
        $this->expectException(QueryException::class);
        $this->version(4, ['clave_apertura' => $clave]);
    }

    public function test_la_base_impide_dos_confirmaciones_de_la_misma_declaracion(): void
    {
        $this->confirmacion();
        $this->expectException(QueryException::class);
        $this->confirmacion();
    }

    public function test_la_base_impide_repetir_una_clave_de_confirmacion_con_otra_declaracion(): void
    {
        $confirmacion = $this->confirmacion();
        $otra = $this->pacienteUc07()->declaracionesSalud()->sole();
        $otraHistoria = HistoriaClinica::create(['paciente_id' => $otra->paciente_id, 'nro_historia_clinica' => 'HC-OTRA', 'activa' => true]);
        $this->expectException(QueryException::class);
        $this->confirmacion(['clave_confirmacion' => $confirmacion->clave_confirmacion, 'declaracion_salud_id' => $otra->id,
            'paciente_id' => $otra->paciente_id, 'historia_clinica_id' => $otraHistoria->id]);
    }

    public function test_la_base_impide_incorporar_dos_veces_sin_usar_los_modelos(): void
    {
        $confirmacion = $this->confirmacion();
        $this->incorporacion($confirmacion, $this->version(1));
        $otraVersion = $this->version(2);
        $this->expectException(QueryException::class);
        DB::table('incorporaciones_declaracion_salud')->insert([
            'version_datos_clinicos_base_id' => $otraVersion->id, 'declaracion_salud_id' => $this->declaracion->id,
            'historia_clinica_id' => $this->historia->id, 'confirmacion_revision_id' => $confirmacion->id,
        ]);
    }

    public function test_no_puede_repetirse_el_dictamen_de_un_bloque(): void
    {
        $confirmacion = $this->confirmacion();
        $this->revision($confirmacion);
        $this->expectException(QueryException::class);
        $this->revision($confirmacion);
    }

    #[DataProvider('referenciasIncompatibles')]
    public function test_las_fk_compuestas_rechazan_cruces_de_paciente_expediente_declaracion_y_autora(string $caso): void
    {
        $otroPaciente = $this->pacienteUc07();
        $otraHistoria = HistoriaClinica::create(['paciente_id' => $otroPaciente->id, 'nro_historia_clinica' => 'HC-AJENA', 'activa' => true]);
        $otraVersion = $this->version(1, ['historia_clinica_id' => $otraHistoria->id]);
        $otraProfesional = $this->profesionalUc07();
        $confirmacion = in_array($caso, ['revision_declaracion', 'revision_autora', 'incorporacion_historia']) ? $this->confirmacion() : null;
        $this->expectException(QueryException::class);
        match ($caso) {
            'paciente' => $this->confirmacion(['paciente_id' => $otroPaciente->id]),
            'historia' => $this->confirmacion(['historia_clinica_id' => $otraHistoria->id]),
            'base' => $this->confirmacion(['version_base_id' => $otraVersion->id]),
            'revision_declaracion' => $this->revision($confirmacion, ['declaracion_salud_id' => $otroPaciente->declaracionesSalud()->sole()->id]),
            'revision_autora' => $this->revision($confirmacion, ['kinesiologa_id' => $otraProfesional->id]),
            'incorporacion_historia' => $this->incorporacion($confirmacion, $otraVersion),
        };
    }

    public static function referenciasIncompatibles(): array
    {
        return array_map(fn ($caso) => [$caso], ['paciente', 'historia', 'base', 'revision_declaracion', 'revision_autora', 'incorporacion_historia']);
    }

    public function test_el_original_solo_cambia_a_revisada_despues_de_registrar_su_incorporacion(): void
    {
        $antes = $this->declaracion->fresh()->getRawOriginal();
        $this->incorporacion($this->confirmacion(), $this->version(1));
        $this->declaracion->update(['estado' => DeclaracionSaludPaciente::ESTADO_REVISADA]);
        $despues = $this->declaracion->fresh()->getRawOriginal();
        unset($antes['estado'], $despues['estado']);
        $this->assertSame($antes, $despues);
        $this->expectException(LogicException::class);
        $this->declaracion->update(['estado' => DeclaracionSaludPaciente::ESTADO_PENDIENTE_REVISION]);
    }

    #[DataProvider('mutacionesProhibidas')]
    public function test_no_sobrescribe_ni_elimina_declaracion_original_o_registros_profesionales(string $caso): void
    {
        $confirmacion = $this->confirmacion();
        $revision = $this->revision($confirmacion);
        $incorporacion = $this->incorporacion($confirmacion, $this->version(1));
        $item = $this->declaracion->items()->create(['tipo' => 'ALERGIA', 'descripcion' => 'Original']);
        $this->expectException(LogicException::class);
        match ($caso) {
            'contenido' => $this->declaracion->update(['altura_cm' => 190]),
            'estado' => $this->declaracion->update(['estado' => 'OTRO']),
            'borrar' => $this->declaracion->delete(),
            'item' => $item->update(['descripcion' => 'Cambio']),
            'borrar_item' => $item->delete(),
            'confirmacion' => $confirmacion->update(['autor_nombre_snapshot' => 'Otra']),
            'revision' => $revision->delete(),
            'incorporacion' => $incorporacion->delete(),
        };
    }

    public static function mutacionesProhibidas(): array
    {
        return array_map(fn ($caso) => [$caso], ['contenido', 'estado', 'borrar', 'item', 'borrar_item', 'confirmacion', 'revision', 'incorporacion']);
    }

    public function test_transicion_a_revisada_sin_incorporacion_se_rechaza(): void
    {
        $this->expectException(LogicException::class);
        $this->declaracion->update(['estado' => DeclaracionSaludPaciente::ESTADO_REVISADA]);
    }

    public function test_fks_impiden_borrar_un_original_referenciado_incluso_por_sql(): void
    {
        $this->confirmacion();
        $this->expectException(QueryException::class);
        DB::table('declaraciones_salud_paciente')->where('id', $this->declaracion->id)->delete();
    }

    public function test_rechaza_altura_con_mas_de_dos_decimales_antes_de_persistir(): void
    {
        $this->expectException(ValidationException::class);
        $this->version(1, ['altura_cm' => '170.255']);
    }

    public function test_no_se_registra_dictamen_observado_sin_justificacion(): void
    {
        $this->expectException(LogicException::class);
        $this->revision($this->confirmacion(), ['dictamen' => 'OBSERVAR', 'observacion_profesional' => '   ']);
    }

    private function version(int $numero, array $cambios = []): VersionDatosClinicosBase
    {
        return VersionDatosClinicosBase::create([...[
            'historia_clinica_id' => $this->historia->id, 'numero_version' => $numero, 'kinesiologa_id' => $this->profesional->id,
            'autor_user_id' => $this->profesional->user_id, 'especialidad_id' => $this->profesional->especialidades()->first()->id,
            'turno_origen_id' => $this->turno->id, 'autor_nombre_snapshot' => 'Autora de prueba', 'especialidad_nombre_snapshot' => 'Especialidad',
            'clave_apertura' => null, 'huella_solicitud' => str_repeat('a', 64), 'origen' => 'REVISION_DECLARACION',
            'procedencia_relevamiento' => 'RELEVAMIENTO_PROFESIONAL', 'relevamientos' => [],
            'resultado_nucleo_familiar' => 'INFORMACION_NO_DISPONIBLE', 'created_at' => now(),
        ], ...$cambios]);
    }

    private function confirmacion(array $cambios = []): ConfirmacionRevisionDeclaracion
    {
        return ConfirmacionRevisionDeclaracion::create([...[
            'declaracion_salud_id' => $this->declaracion->id, 'historia_clinica_id' => $this->historia->id, 'paciente_id' => $this->paciente->id,
            'kinesiologa_id' => $this->profesional->id, 'autor_user_id' => $this->profesional->user_id,
            'especialidad_id' => $this->profesional->especialidades()->first()->id, 'turno_origen_id' => $this->turno->id,
            'autor_nombre_snapshot' => 'Autora de prueba', 'especialidad_nombre_snapshot' => 'Especialidad',
            'clave_confirmacion' => (string) Str::uuid(), 'huella_declaracion' => str_repeat('b', 64), 'huella_resultado' => str_repeat('c', 64),
            'linaje_resultado' => [], 'accion' => ConfirmacionRevisionDeclaracion::ACCION, 'confirmada_at' => now(),
        ], ...$cambios]);
    }

    private function revision(ConfirmacionRevisionDeclaracion $confirmacion, array $cambios = []): RevisionDeclaracionSalud
    {
        return $confirmacion->dictamenes()->create([...[
            'declaracion_salud_id' => $this->declaracion->id, 'kinesiologa_id' => $this->profesional->id,
            'bloque' => 'peso_kg', 'dictamen' => 'VALIDAR', 'datos_originales' => ['valor' => 70],
            'datos_admitidos' => ['valor' => 70], 'procedencias' => ['origen' => 'DECLARACION_PACIENTE'], 'created_at' => now(),
        ], ...$cambios]);
    }

    private function incorporacion(ConfirmacionRevisionDeclaracion $confirmacion, VersionDatosClinicosBase $version): IncorporacionDeclaracionSalud
    {
        return IncorporacionDeclaracionSalud::create(['version_datos_clinicos_base_id' => $version->id,
            'declaracion_salud_id' => $this->declaracion->id, 'confirmacion_revision_id' => $confirmacion->id,
            'historia_clinica_id' => $this->historia->id]);
    }
}
