<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Models\Calle;
use App\Models\Localidad;
use App\Models\Paciente;
use App\Models\Provincia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class GeografiaControllerTest extends TestCase
{
    use RefreshDatabase;

    private int $secuenciaCalle = 1;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (RoleEnum::cases() as $rol) {
            Role::findOrCreate($rol->value, 'web');
        }
    }

    public function test_guest_recibe_401(): void
    {
        $this->getJson(route('geografia.provincias.index'))
            ->assertUnauthorized();
    }

    public function test_paciente_autenticado_puede_acceder(): void
    {
        $this->actingAs($this->usuarioConRol(RoleEnum::PACIENTE))
            ->getJson(route('geografia.provincias.index'))
            ->assertOk();
    }

    public function test_paciente_incompleto_puede_acceder(): void
    {
        $paciente = $this->usuarioConRol(RoleEnum::PACIENTE);

        $this->assertFalse($paciente->perfilCompleto());

        $this->actingAs($paciente)
            ->getJson(route('geografia.provincias.index'))
            ->assertOk();
    }

    public function test_paciente_sin_email_verificado_puede_acceder(): void
    {
        $paciente = $this->usuarioConRol(RoleEnum::PACIENTE, false);

        $this->actingAs($paciente)
            ->getJson(route('geografia.provincias.index'))
            ->assertOk();
    }

    #[DataProvider('rolesNoPacientes')]
    public function test_roles_no_pacientes_reciben_403(RoleEnum $rol): void
    {
        $this->actingAs($this->usuarioConRol($rol))
            ->getJson(route('geografia.provincias.index'))
            ->assertForbidden();
    }

    /**
     * @return array<string, array{RoleEnum}>
     */
    public static function rolesNoPacientes(): array
    {
        return [
            'kinesiologo' => [RoleEnum::KINESIOLOGO],
            'administrador' => [RoleEnum::ADMINISTRADOR],
            'superadmin' => [RoleEnum::SUPERADMIN],
        ];
    }

    public function test_provincias_devuelve_campos_exactos_y_orden_aprobado(): void
    {
        $paciente = $this->usuarioConRol(RoleEnum::PACIENTE);
        $segundaAlfa = $this->crearProvincia('54', 'Alfa');
        $primeraAlfa = $this->crearProvincia('06', 'Alfa');
        $beta = $this->crearProvincia('30', 'Beta');

        $respuesta = $this->actingAs($paciente)
            ->getJson(route('geografia.provincias.index'));

        $respuesta->assertOk()->assertExactJson([
            ['id' => $segundaAlfa->id, 'nombre' => 'Alfa'],
            ['id' => $primeraAlfa->id, 'nombre' => 'Alfa'],
            ['id' => $beta->id, 'nombre' => 'Beta'],
        ]);
    }

    public function test_provincias_vacio_devuelve_array_vacio(): void
    {
        $this->actingAs($this->usuarioConRol(RoleEnum::PACIENTE))
            ->getJson(route('geografia.provincias.index'))
            ->assertOk()
            ->assertExactJson([]);
    }

    public function test_localidades_filtra_ordena_y_devuelve_campos_exactos(): void
    {
        $paciente = $this->usuarioConRol(RoleEnum::PACIENTE);
        $provincia = $this->crearProvincia('06', 'Provincia Uno');
        $otraProvincia = $this->crearProvincia('54', 'Provincia Dos');

        $carolinaGoya1 = $this->crearLocalidad(
            $provincia,
            '01000002',
            'Carolina',
            'Goya'
        );
        $carolinaGoya2 = $this->crearLocalidad(
            $provincia,
            '01000003',
            'Carolina',
            'Goya'
        );
        $carolinaBellaVista = $this->crearLocalidad(
            $provincia,
            '01000001',
            'Carolina',
            'Bella Vista'
        );
        $zeta = $this->crearLocalidad(
            $provincia,
            '01000004',
            'Zeta',
            'Capital'
        );
        $this->crearLocalidad(
            $otraProvincia,
            '02000001',
            'Carolina',
            'Goya'
        );

        $respuesta = $this->actingAs($paciente)->getJson(route(
            'geografia.localidades.index',
            ['provincia' => $provincia]
        ));

        $respuesta->assertOk()->assertExactJson([
            [
                'id' => $carolinaBellaVista->id,
                'nombre' => 'Carolina',
                'departamento_nombre' => 'Bella Vista',
                'codigo_censal' => '01000001',
            ],
            [
                'id' => $carolinaGoya1->id,
                'nombre' => 'Carolina',
                'departamento_nombre' => 'Goya',
                'codigo_censal' => '01000002',
            ],
            [
                'id' => $carolinaGoya2->id,
                'nombre' => 'Carolina',
                'departamento_nombre' => 'Goya',
                'codigo_censal' => '01000003',
            ],
            [
                'id' => $zeta->id,
                'nombre' => 'Zeta',
                'departamento_nombre' => 'Capital',
                'codigo_censal' => '01000004',
            ],
        ]);

        $this->assertIsString(
            $respuesta->json('0.codigo_censal')
        );
        $this->assertStringStartsWith(
            '0',
            $respuesta->json('0.codigo_censal')
        );
    }

    public function test_provincia_valida_sin_localidades_devuelve_array_vacio(): void
    {
        $provincia = $this->crearProvincia('06', 'Sin localidades');

        $this->actingAs($this->usuarioConRol(RoleEnum::PACIENTE))
            ->getJson(route(
                'geografia.localidades.index',
                ['provincia' => $provincia]
            ))
            ->assertOk()
            ->assertExactJson([]);
    }

    public function test_provincia_inexistente_devuelve_404(): void
    {
        $this->actingAs($this->usuarioConRol(RoleEnum::PACIENTE))
            ->getJson('/geografia/provincias/999999/localidades')
            ->assertNotFound();
    }

    public function test_id_de_provincia_mal_formado_devuelve_404(): void
    {
        $this->actingAs($this->usuarioConRol(RoleEnum::PACIENTE))
            ->getJson('/geografia/provincias/no-numerica/localidades')
            ->assertNotFound();
    }

    #[DataProvider('terminosInvalidos')]
    public function test_q_invalido_devuelve_422(array $query): void
    {
        $localidad = $this->crearLocalidadBase();

        $this->actingAs($this->usuarioConRol(RoleEnum::PACIENTE))
            ->getJson(route(
                'geografia.calles.index',
                ['localidad' => $localidad] + $query
            ))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('q');
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function terminosInvalidos(): array
    {
        return [
            'ausente' => [[]],
            'vacio' => [['q' => '']],
            'solo espacios' => [['q' => '   ']],
            'un caracter' => [['q' => 'A']],
            '151 caracteres' => [['q' => str_repeat('A', 151)]],
            'no string' => [['q' => ['AB']]],
        ];
    }

    #[DataProvider('terminosLimiteValidos')]
    public function test_q_en_longitudes_limite_es_valido(string $termino): void
    {
        $localidad = $this->crearLocalidadBase();

        $this->actingAs($this->usuarioConRol(RoleEnum::PACIENTE))
            ->getJson(route('geografia.calles.index', [
                'localidad' => $localidad,
                'q' => $termino,
            ]))
            ->assertOk();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function terminosLimiteValidos(): array
    {
        return [
            'dos caracteres' => ['AB'],
            '150 caracteres' => [str_repeat('A', 150)],
        ];
    }

    public function test_q_se_recorta_antes_de_buscar(): void
    {
        $localidad = $this->crearLocalidadBase();
        $this->crearCalle($localidad, 'SAUCE');

        $this->actingAs($this->usuarioConRol(RoleEnum::PACIENTE))
            ->getJson(route('geografia.calles.index', [
                'localidad' => $localidad,
                'q' => '  SA  ',
            ]))
            ->assertOk()
            ->assertExactJson([['nombre' => 'SAUCE']]);
    }

    public function test_calles_filtra_estrictamente_por_localidad(): void
    {
        $provincia = $this->crearProvincia('06', 'Provincia');
        $localidad = $this->crearLocalidad(
            $provincia,
            '01000001',
            'Localidad Uno',
            'Departamento'
        );
        $otraLocalidad = $this->crearLocalidad(
            $provincia,
            '01000002',
            'Localidad Dos',
            'Departamento'
        );
        $this->crearCalle($localidad, 'SAN ALBERTO');
        $this->crearCalle($otraLocalidad, 'SAN MARTIN');

        $this->actingAs($this->usuarioConRol(RoleEnum::PACIENTE))
            ->getJson(route('geografia.calles.index', [
                'localidad' => $localidad,
                'q' => 'SAN',
            ]))
            ->assertOk()
            ->assertExactJson([['nombre' => 'SAN ALBERTO']]);
    }

    public function test_calles_devuelve_prefix_primero_y_luego_contains(): void
    {
        $localidad = $this->crearLocalidadBase();

        foreach ([
            'SAN ANDRES',
            'AVENIDA SAN MARTIN',
            'SAN ALBERTO',
            'PASAJE SAN JOSE',
        ] as $nombre) {
            $this->crearCalle($localidad, $nombre);
        }

        $this->actingAs($this->usuarioConRol(RoleEnum::PACIENTE))
            ->getJson(route('geografia.calles.index', [
                'localidad' => $localidad,
                'q' => 'SAN',
            ]))
            ->assertOk()
            ->assertExactJson([
                ['nombre' => 'SAN ALBERTO'],
                ['nombre' => 'SAN ANDRES'],
                ['nombre' => 'AVENIDA SAN MARTIN'],
                ['nombre' => 'PASAJE SAN JOSE'],
            ]);
    }

    public function test_diez_prefix_completan_el_limite_sin_incluir_contains(): void
    {
        $localidad = $this->crearLocalidadBase();

        for ($indice = 1; $indice <= 11; $indice++) {
            $this->crearCalle(
                $localidad,
                'SAN '.str_pad((string) $indice, 2, '0', STR_PAD_LEFT)
            );
        }
        $this->crearCalle($localidad, 'AVENIDA SAN MARTIN');

        $respuesta = $this->actingAs(
            $this->usuarioConRol(RoleEnum::PACIENTE)
        )->getJson(route('geografia.calles.index', [
            'localidad' => $localidad,
            'q' => 'SAN',
        ]));

        $respuesta->assertOk()->assertJsonCount(10);
        $this->assertNotContains(
            'AVENIDA SAN MARTIN',
            array_column($respuesta->json(), 'nombre')
        );
    }

    public function test_contains_completa_hasta_un_maximo_total_de_diez(): void
    {
        $localidad = $this->crearLocalidadBase();
        $this->crearCalle($localidad, 'SAN ALBERTO');
        $this->crearCalle($localidad, 'SAN ANDRES');

        for ($indice = 1; $indice <= 10; $indice++) {
            $this->crearCalle(
                $localidad,
                'AVENIDA SAN '.str_pad((string) $indice, 2, '0', STR_PAD_LEFT)
            );
        }

        $respuesta = $this->actingAs(
            $this->usuarioConRol(RoleEnum::PACIENTE)
        )->getJson(route('geografia.calles.index', [
            'localidad' => $localidad,
            'q' => 'SAN',
        ]));

        $respuesta->assertOk()->assertJsonCount(10);
        $this->assertSame('SAN ALBERTO', $respuesta->json('0.nombre'));
        $this->assertSame('SAN ANDRES', $respuesta->json('1.nombre'));
    }

    public function test_distinct_se_aplica_antes_del_limite(): void
    {
        $localidad = $this->crearLocalidadBase();

        for ($indice = 1; $indice <= 12; $indice++) {
            $this->crearCalle($localidad, 'ALFA');
        }

        for ($indice = 1; $indice <= 9; $indice++) {
            $this->crearCalle(
                $localidad,
                'ALFA '.str_pad((string) $indice, 2, '0', STR_PAD_LEFT)
            );
        }

        $respuesta = $this->actingAs(
            $this->usuarioConRol(RoleEnum::PACIENTE)
        )->getJson(route('geografia.calles.index', [
            'localidad' => $localidad,
            'q' => 'AL',
        ]));

        $respuesta->assertOk()->assertJsonCount(10);
        $nombres = array_column($respuesta->json(), 'nombre');
        $this->assertCount(10, array_unique($nombres));
        $this->assertSame(1, array_count_values($nombres)['ALFA']);
    }

    #[DataProvider('terminosConCaracteresLiterales')]
    public function test_caracteres_like_se_buscan_literalmente(
        string $termino,
        string $coincidencia,
        string $noCoincidencia
    ): void {
        $localidad = $this->crearLocalidadBase();
        $this->crearCalle($localidad, $coincidencia);
        $this->crearCalle($localidad, $noCoincidencia);

        $this->actingAs($this->usuarioConRol(RoleEnum::PACIENTE))
            ->getJson(route('geografia.calles.index', [
                'localidad' => $localidad,
                'q' => $termino,
            ]))
            ->assertOk()
            ->assertExactJson([['nombre' => $coincidencia]]);
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function terminosConCaracteresLiterales(): array
    {
        return [
            'porcentaje' => ['100%', '100% REAL', '100X REAL'],
            'guion bajo' => ['A_', 'A_B', 'ACB'],
            'admiracion' => ['A!', 'A!B', 'AAB'],
            'barra invertida' => ['A\\', 'A\\B', 'AB'],
        ];
    }

    public function test_localidad_inexistente_devuelve_404(): void
    {
        $this->actingAs($this->usuarioConRol(RoleEnum::PACIENTE))
            ->getJson('/geografia/localidades/999999/calles?q=AB')
            ->assertNotFound();
    }

    public function test_id_de_localidad_mal_formado_devuelve_404(): void
    {
        $this->actingAs($this->usuarioConRol(RoleEnum::PACIENTE))
            ->getJson('/geografia/localidades/no-numerica/calles?q=AB')
            ->assertNotFound();
    }

    public function test_busqueda_sin_resultados_devuelve_array_vacio(): void
    {
        $localidad = $this->crearLocalidadBase();

        $this->actingAs($this->usuarioConRol(RoleEnum::PACIENTE))
            ->getJson(route('geografia.calles.index', [
                'localidad' => $localidad,
                'q' => 'ZZ',
            ]))
            ->assertOk()
            ->assertExactJson([]);
    }

    private function usuarioConRol(
        RoleEnum $rol,
        bool $emailVerificado = true
    ): User {
        $user = User::factory()->create([
            'dni' => null,
            'fecha_nacimiento' => null,
            'sexo' => null,
            'telefono' => null,
            'activo' => true,
            'email_verified_at' => $emailVerificado ? now() : null,
        ]);

        $user->assignRole($rol->value);

        if ($rol === RoleEnum::PACIENTE) {
            Paciente::create([
                'user_id' => $user->id,
                'activo' => true,
            ]);
        }

        return $user->fresh();
    }

    private function crearProvincia(string $georefId, string $nombre): Provincia
    {
        return Provincia::create([
            'georef_id' => $georefId,
            'nombre' => $nombre,
        ]);
    }

    private function crearLocalidad(
        Provincia $provincia,
        string $georefId,
        string $nombre,
        string $departamento
    ): Localidad {
        return Localidad::create([
            'georef_id' => $georefId,
            'provincia_id' => $provincia->id,
            'nombre' => $nombre,
            'departamento_nombre' => $departamento,
        ]);
    }

    private function crearLocalidadBase(): Localidad
    {
        $provincia = $this->crearProvincia('06', 'Provincia');

        return $this->crearLocalidad(
            $provincia,
            '01000001',
            'Localidad',
            'Departamento'
        );
    }

    private function crearCalle(Localidad $localidad, string $nombre): Calle
    {
        return Calle::create([
            'georef_id' => str_pad(
                (string) $this->secuenciaCalle++,
                15,
                '0',
                STR_PAD_LEFT
            ),
            'localidad_id' => $localidad->id,
            'nombre' => $nombre,
        ]);
    }
}
