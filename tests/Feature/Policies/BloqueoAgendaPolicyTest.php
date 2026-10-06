<?php

namespace Tests\Feature\Policies;

use App\Enums\RoleEnum;
use App\Models\BloqueoAgenda;
use App\Models\Kinesiologa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class BloqueoAgendaPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (RoleEnum::cases() as $role) {
            Role::findOrCreate($role->value, 'web');
        }
    }

    public function test_solo_kinesiologo_puede_usar_abilities_de_clase(): void
    {
        $kinesiologo = $this->usuarioConRol(RoleEnum::KINESIOLOGO);

        foreach (['viewAny', 'create'] as $ability) {
            $this->assertTrue(
                Gate::forUser($kinesiologo)->allows($ability, BloqueoAgenda::class),
                "KINESIOLOGO debería poder ejecutar {$ability}."
            );
        }

        foreach ([RoleEnum::PACIENTE, RoleEnum::ADMINISTRADOR, RoleEnum::SUPERADMIN] as $role) {
            $user = $this->usuarioConRol($role);

            foreach (['viewAny', 'create'] as $ability) {
                $this->assertFalse(
                    Gate::forUser($user)->allows($ability, BloqueoAgenda::class),
                    "{$role->name} no debería poder ejecutar {$ability}."
                );
            }
        }
    }

    public function test_propietaria_puede_ver_bloqueos_de_cualquier_estado_y_otra_no(): void
    {
        $propietaria = $this->kinesiologaConPerfil('propietaria');
        $otra = $this->kinesiologaConPerfil('otra');
        $bloqueos = [
            $this->bloqueo($propietaria->kinesiologa, '2026-10-07 08:00:00', '2026-10-07 10:00:00'),
            $this->bloqueo($propietaria->kinesiologa, '2026-10-06 11:00:00', '2026-10-06 13:00:00'),
            $this->bloqueo($propietaria->kinesiologa, '2026-10-05 08:00:00', '2026-10-05 10:00:00'),
            $this->bloqueo(
                $propietaria->kinesiologa,
                '2026-10-07 08:00:00',
                '2026-10-07 10:00:00',
                '2026-10-05 10:00:00'
            ),
        ];

        foreach ($bloqueos as $bloqueo) {
            $this->assertTrue(Gate::forUser($propietaria)->allows('view', $bloqueo));
            $this->assertFalse(Gate::forUser($otra)->allows('view', $bloqueo));
        }
    }

    public function test_anular_depende_de_ownership_y_no_de_la_posicion_temporal(): void
    {
        $propietaria = $this->kinesiologaConPerfil('propietaria');
        $otra = $this->kinesiologaConPerfil('otra');
        $proximo = $this->bloqueo(
            $propietaria->kinesiologa,
            '2026-10-07 08:00:00',
            '2026-10-07 10:00:00'
        );
        $enCurso = $this->bloqueo(
            $propietaria->kinesiologa,
            '2026-10-06 11:00:00',
            '2026-10-06 13:00:00'
        );
        $pasado = $this->bloqueo(
            $propietaria->kinesiologa,
            '2026-10-05 08:00:00',
            '2026-10-05 10:00:00'
        );
        $anulado = $this->bloqueo(
            $propietaria->kinesiologa,
            '2026-10-07 08:00:00',
            '2026-10-07 10:00:00',
            '2026-10-05 10:00:00'
        );
        $ajeno = $this->bloqueo(
            $otra->kinesiologa,
            '2026-10-07 08:00:00',
            '2026-10-07 10:00:00'
        );

        foreach ([$proximo, $enCurso, $pasado] as $bloqueo) {
            $this->assertTrue(Gate::forUser($propietaria)->allows('anular', $bloqueo));
        }

        $this->assertFalse(Gate::forUser($propietaria)->allows('anular', $anulado));
        $this->assertFalse(Gate::forUser($propietaria)->allows('anular', $ajeno));
    }

    public function test_usuario_kinesiologo_sin_perfil_no_es_propietario_de_recursos(): void
    {
        $actor = $this->usuarioConRol(RoleEnum::KINESIOLOGO);
        $propietaria = $this->kinesiologaConPerfil('propietaria');
        $bloqueo = $this->bloqueo(
            $propietaria->kinesiologa,
            '2026-10-07 08:00:00',
            '2026-10-07 10:00:00'
        );

        $this->assertFalse(Gate::forUser($actor)->allows('view', $bloqueo));
        $this->assertFalse(Gate::forUser($actor)->allows('anular', $bloqueo));
    }

    public function test_update_y_delete_siempre_estan_denegados(): void
    {
        $propietaria = $this->kinesiologaConPerfil('propietaria');
        $bloqueo = $this->bloqueo(
            $propietaria->kinesiologa,
            '2026-10-07 08:00:00',
            '2026-10-07 10:00:00'
        );

        $this->assertFalse(Gate::forUser($propietaria)->allows('update', $bloqueo));
        $this->assertFalse(Gate::forUser($propietaria)->allows('delete', $bloqueo));

        $superadmin = $this->usuarioConRol(RoleEnum::SUPERADMIN);
        $this->assertFalse(Gate::forUser($superadmin)->allows('update', $bloqueo));
        $this->assertFalse(Gate::forUser($superadmin)->allows('delete', $bloqueo));
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

    private function bloqueo(
        Kinesiologa $kinesiologa,
        string $inicio,
        string $fin,
        ?string $anuladoAt = null
    ): BloqueoAgenda {
        return BloqueoAgenda::factory()
            ->for($kinesiologa)
            ->create([
                'inicio_at' => $inicio,
                'fin_at' => $fin,
                'anulado_at' => $anuladoAt,
                'motivo_anulacion' => $anuladoAt === null ? null : 'Anulación de prueba',
            ]);
    }
}
