<?php

namespace Tests\Feature\Policies;

use App\Enums\RoleEnum;
use App\Models\Kinesiologa;
use App\Models\MatriculaProfesionalKinesiologia;
use App\Models\TituloProfesional;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MatriculaProfesionalKinesiologiaPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Role::findOrCreate(RoleEnum::KINESIOLOGO->value, 'web');
        Role::findOrCreate(RoleEnum::PACIENTE->value, 'web');
    }

    private function kinesiologaConPerfil(
        string $emailPrefix = 'kinesiologa'
    ): User {
        $titulo = TituloProfesional::query()->firstOrCreate(
            ['slug' => 'kinesiologo'],
            [
                'nombre' => 'Kinesiólogo',
                'activo' => true,
            ]
        );

        $user = User::factory()->create([
            'email' => $emailPrefix . '_' . uniqid() . '@example.com',
            'activo' => true,
        ]);

        $user->assignRole(RoleEnum::KINESIOLOGO->value);

        Kinesiologa::create([
            'user_id' => $user->id,
            'titulo_profesional_id' => $titulo->id,
            'consultorio_id' => null,
        ]);

        return $user->fresh('kinesiologa');
    }

    private function paciente(): User
    {
        $user = User::factory()->create([
            'email' => 'paciente_' . uniqid() . '@example.com',
            'activo' => true,
        ]);

        $user->assignRole(RoleEnum::PACIENTE->value);

        return $user;
    }

    private function matriculaDe(
        User $user
    ): MatriculaProfesionalKinesiologia {
        return new MatriculaProfesionalKinesiologia([
            'kinesiologa_id' => $user->kinesiologa->id,
            'nro_matricula_profesional' => '0755',
            'jurisdiccion_id' => 1,
            'colegio_emisor_id' => 1,
            'activa' => true,
        ]);
    }

    public function test_kinesiologa_can_view_matriculas_and_create_matriculas(): void
    {
        $user = $this->kinesiologaConPerfil();

        $this->assertTrue(
            Gate::forUser($user)->allows(
                'viewAny',
                MatriculaProfesionalKinesiologia::class
            )
        );

        $this->assertTrue(
            Gate::forUser($user)->allows(
                'create',
                MatriculaProfesionalKinesiologia::class
            )
        );
    }

    public function test_paciente_cannot_view_or_create_matriculas(): void
    {
        $user = $this->paciente();

        $this->assertFalse(
            Gate::forUser($user)->allows(
                'viewAny',
                MatriculaProfesionalKinesiologia::class
            )
        );

        $this->assertFalse(
            Gate::forUser($user)->allows(
                'create',
                MatriculaProfesionalKinesiologia::class
            )
        );
    }

    public function test_kinesiologa_can_update_own_matricula(): void
    {
        $user = $this->kinesiologaConPerfil();
        $matricula = $this->matriculaDe($user);

        $this->assertTrue(
            Gate::forUser($user)->allows('update', $matricula)
        );
    }

    public function test_kinesiologa_cannot_update_foreign_matricula(): void
    {
        $user = $this->kinesiologaConPerfil('propietaria');
        $other = $this->kinesiologaConPerfil('otra');
        $foreignMatricula = $this->matriculaDe($other);

        $this->assertFalse(
            Gate::forUser($user)->allows('update', $foreignMatricula)
        );
    }

    public function test_paciente_cannot_update_matricula(): void
    {
        $owner = $this->kinesiologaConPerfil();
        $paciente = $this->paciente();
        $matricula = $this->matriculaDe($owner);

        $this->assertFalse(
            Gate::forUser($paciente)->allows('update', $matricula)
        );
    }

    public function test_kinesiologa_can_toggle_own_matricula(): void
    {
        $user = $this->kinesiologaConPerfil();
        $matricula = $this->matriculaDe($user);

        $this->assertTrue(
            Gate::forUser($user)->allows('toggleActiva', $matricula)
        );
    }

    public function test_kinesiologa_cannot_toggle_foreign_matricula(): void
    {
        $user = $this->kinesiologaConPerfil('propietaria');
        $other = $this->kinesiologaConPerfil('otra');
        $foreignMatricula = $this->matriculaDe($other);

        $this->assertFalse(
            Gate::forUser($user)->allows(
                'toggleActiva',
                $foreignMatricula
            )
        );
    }

    public function test_paciente_cannot_toggle_matricula(): void
    {
        $owner = $this->kinesiologaConPerfil();
        $paciente = $this->paciente();
        $matricula = $this->matriculaDe($owner);

        $this->assertFalse(
            Gate::forUser($paciente)->allows('toggleActiva', $matricula)
        );
    }
}
