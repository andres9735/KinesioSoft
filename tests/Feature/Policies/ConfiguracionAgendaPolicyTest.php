<?php

namespace Tests\Feature\Policies;

use App\Enums\RoleEnum;
use App\Models\BloqueoAgenda;
use App\Models\ConfiguracionAgenda;
use App\Models\FranjaHorariaAgenda;
use App\Models\Kinesiologa;
use App\Models\User;
use App\Policies\BloqueoAgendaPolicy;
use App\Policies\ConfiguracionAgendaPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ConfiguracionAgendaPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (RoleEnum::cases() as $role) {
            Role::findOrCreate($role->value, 'web');
        }

        CarbonImmutable::setTestNow(CarbonImmutable::create(
            2026,
            10,
            6,
            12,
            0,
            0,
            config('app.timezone')
        ));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_laravel_descubre_las_policies_de_agenda_por_convencion(): void
    {
        $this->assertInstanceOf(
            ConfiguracionAgendaPolicy::class,
            Gate::getPolicyFor(ConfiguracionAgenda::class)
        );
        $this->assertInstanceOf(
            BloqueoAgendaPolicy::class,
            Gate::getPolicyFor(BloqueoAgenda::class)
        );
    }

    public function test_solo_kinesiologo_puede_usar_abilities_de_clase(): void
    {
        $kinesiologo = $this->usuarioConRol(RoleEnum::KINESIOLOGO);

        foreach (['viewAny', 'create', 'cambiarDuracion'] as $ability) {
            $this->assertTrue(
                Gate::forUser($kinesiologo)->allows($ability, ConfiguracionAgenda::class),
                "KINESIOLOGO debería poder ejecutar {$ability}."
            );
        }

        foreach ([RoleEnum::PACIENTE, RoleEnum::ADMINISTRADOR, RoleEnum::SUPERADMIN] as $role) {
            $user = $this->usuarioConRol($role);

            foreach (['viewAny', 'create', 'cambiarDuracion'] as $ability) {
                $this->assertFalse(
                    Gate::forUser($user)->allows($ability, ConfiguracionAgenda::class),
                    "{$role->name} no debería poder ejecutar {$ability}."
                );
            }
        }
    }

    public function test_propietaria_puede_ver_configuraciones_de_cualquier_estado_y_otra_no(): void
    {
        $propietaria = $this->kinesiologaConPerfil('propietaria');
        $otra = $this->kinesiologaConPerfil('otra');
        $configuraciones = [
            $this->configuracion($propietaria->kinesiologa, '2026-10-01'),
            $this->configuracion($propietaria->kinesiologa, '2026-10-10'),
            $this->configuracion($propietaria->kinesiologa, '2026-09-01', '2026-10-05'),
            $this->configuracion(
                $propietaria->kinesiologa,
                '2026-10-12',
                null,
                '2026-10-05 10:00:00'
            ),
        ];

        foreach ($configuraciones as $configuracion) {
            $this->assertTrue(Gate::forUser($propietaria)->allows('view', $configuracion));
            $this->assertFalse(Gate::forUser($otra)->allows('view', $configuracion));
        }
    }

    public function test_update_solo_autoriza_configuracion_propia_estrictamente_futura_no_anulada(): void
    {
        $propietaria = $this->kinesiologaConPerfil('propietaria');
        $otra = $this->kinesiologaConPerfil('otra');
        $casos = [
            [$this->configuracion($propietaria->kinesiologa, '2026-10-07'), true],
            [$this->configuracion($propietaria->kinesiologa, '2026-10-06'), false],
            [$this->configuracion($propietaria->kinesiologa, '2026-10-01'), false],
            [$this->configuracion($propietaria->kinesiologa, '2026-09-01', '2026-10-05'), false],
            [
                $this->configuracion(
                    $propietaria->kinesiologa,
                    '2026-10-07',
                    null,
                    '2026-10-05 10:00:00'
                ),
                false,
            ],
            [$this->configuracion($otra->kinesiologa, '2026-10-07'), false],
        ];

        foreach ($casos as [$configuracion, $esperado]) {
            $this->assertSame(
                $esperado,
                Gate::forUser($propietaria)->allows('update', $configuracion)
            );
        }
    }

    public function test_anular_solo_autoriza_configuracion_propia_estrictamente_futura_no_anulada(): void
    {
        $propietaria = $this->kinesiologaConPerfil('propietaria');
        $otra = $this->kinesiologaConPerfil('otra');
        $casos = [
            [$this->configuracion($propietaria->kinesiologa, '2026-10-07'), true],
            [$this->configuracion($propietaria->kinesiologa, '2026-10-06'), false],
            [$this->configuracion($propietaria->kinesiologa, '2026-10-01'), false],
            [$this->configuracion($propietaria->kinesiologa, '2026-09-01', '2026-10-05'), false],
            [
                $this->configuracion(
                    $propietaria->kinesiologa,
                    '2026-10-07',
                    null,
                    '2026-10-05 10:00:00'
                ),
                false,
            ],
            [$this->configuracion($otra->kinesiologa, '2026-10-07'), false],
        ];

        foreach ($casos as [$configuracion, $esperado]) {
            $this->assertSame(
                $esperado,
                Gate::forUser($propietaria)->allows('anular', $configuracion)
            );
        }
    }

    public function test_usuario_kinesiologo_sin_perfil_no_es_propietario_de_recursos(): void
    {
        $actor = $this->usuarioConRol(RoleEnum::KINESIOLOGO);
        $propietaria = $this->kinesiologaConPerfil('propietaria');
        $configuracion = $this->configuracion($propietaria->kinesiologa, '2026-10-07');

        $this->assertFalse(Gate::forUser($actor)->allows('view', $configuracion));
        $this->assertFalse(Gate::forUser($actor)->allows('update', $configuracion));
        $this->assertFalse(Gate::forUser($actor)->allows('anular', $configuracion));
    }

    public function test_delete_siempre_esta_denegado(): void
    {
        $propietaria = $this->kinesiologaConPerfil('propietaria');
        $configuracion = $this->configuracion($propietaria->kinesiologa, '2026-10-07');

        $this->assertFalse(Gate::forUser($propietaria)->allows('delete', $configuracion));
        $this->assertFalse(
            Gate::forUser($this->usuarioConRol(RoleEnum::SUPERADMIN))
                ->allows('delete', $configuracion)
        );
    }

    private function usuarioConRol(RoleEnum $role, string $emailPrefix = 'usuario'): User
    {
        $user = User::factory()->create([
            'email' => $emailPrefix.'_'.uniqid().'@example.com',
            'activo' => true,
        ]);
        $user->assignRole($role->value);

        return $user;
    }

    private function kinesiologaConPerfil(string $emailPrefix): User
    {
        $user = $this->usuarioConRol(RoleEnum::KINESIOLOGO, $emailPrefix);

        Kinesiologa::create([
            'user_id' => $user->id,
            'titulo_profesional_id' => null,
            'consultorio_id' => null,
        ]);

        return $user->fresh('kinesiologa');
    }

    private function configuracion(
        Kinesiologa $kinesiologa,
        string $desde,
        ?string $hasta = null,
        ?string $anuladoAt = null
    ): ConfiguracionAgenda {
        $configuracion = ConfiguracionAgenda::factory()
            ->for($kinesiologa)
            ->create([
                'vigencia_desde' => $desde,
                'vigencia_hasta' => $hasta,
                'anulado_at' => $anuladoAt,
                'motivo_anulacion' => $anuladoAt === null ? null : 'Anulación de prueba',
            ]);

        FranjaHorariaAgenda::factory()
            ->for($configuracion, 'configuracionAgenda')
            ->create();

        return $configuracion;
    }
}
