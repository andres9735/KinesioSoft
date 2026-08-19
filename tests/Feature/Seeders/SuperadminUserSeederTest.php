<?php

namespace Tests\Feature\Seeders;

use App\Enums\RoleEnum;
use App\Models\User;
use Database\Seeders\SuperadminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SuperadminUserSeederTest extends TestCase
{
    use RefreshDatabase;

    private const EMAIL = 'bootstrap.superadmin@example.com';

    private const PASSWORD = 'Bootstrap-Password-123!';

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]
            ->forgetCachedPermissions();

        Role::findOrCreate(
            RoleEnum::SUPERADMIN->value,
            'web'
        );

        Role::findOrCreate(
            RoleEnum::PACIENTE->value,
            'web'
        );

        $this->configurarBootstrap();
    }

    public function test_fails_when_email_is_not_configured(): void
    {
        $this->configurarBootstrap([
            'email' => null,
        ]);

        $this->expectException(RuntimeException::class);

        $this->expectExceptionMessage(
            'Debe configurar KINESIOSOFT_SUPERADMIN_EMAIL y ' .
                'KINESIOSOFT_SUPERADMIN_PASSWORD en el archivo .env.'
        );

        $this->seed(SuperadminUserSeeder::class);
    }

    public function test_fails_when_password_is_not_configured(): void
    {
        $this->configurarBootstrap([
            'password' => null,
        ]);

        $this->expectException(RuntimeException::class);

        $this->expectExceptionMessage(
            'Debe configurar KINESIOSOFT_SUPERADMIN_EMAIL y ' .
                'KINESIOSOFT_SUPERADMIN_PASSWORD en el archivo .env.'
        );

        $this->seed(SuperadminUserSeeder::class);
    }

    public function test_creates_active_verified_superadmin_with_expected_data(): void
    {
        $this->seed(SuperadminUserSeeder::class);

        $superadmin = User::query()
            ->where('email', self::EMAIL)
            ->sole();

        $this->assertSame(
            'Superadministrador',
            $superadmin->name
        );

        $this->assertSame(
            'Principal',
            $superadmin->apellido
        );

        $this->assertSame(
            self::EMAIL,
            $superadmin->email
        );

        $this->assertTrue($superadmin->activo);

        $this->assertNotNull(
            $superadmin->email_verified_at
        );

        $this->assertTrue(
            Hash::check(
                self::PASSWORD,
                $superadmin->password
            )
        );

        $this->assertSame(
            [RoleEnum::SUPERADMIN->value],
            $superadmin->getRoleNames()->all()
        );

        $this->assertDatabaseCount('users', 1);
    }

    public function test_can_run_again_without_duplicating_superadmin(): void
    {
        $this->seed(SuperadminUserSeeder::class);

        $superadminInicial = User::query()
            ->where('email', self::EMAIL)
            ->sole();

        $this->seed(SuperadminUserSeeder::class);

        $superadminFinal = User::query()
            ->where('email', self::EMAIL)
            ->sole();

        $this->assertSame(
            $superadminInicial->id,
            $superadminFinal->id
        );

        $this->assertDatabaseCount('users', 1);

        $this->assertSame(
            [RoleEnum::SUPERADMIN->value],
            $superadminFinal->getRoleNames()->all()
        );
    }

    public function test_second_run_does_not_rotate_existing_password(): void
    {
        $this->seed(SuperadminUserSeeder::class);

        $superadmin = User::query()
            ->where('email', self::EMAIL)
            ->sole();

        $hashOriginal = $superadmin->password;

        $this->configurarBootstrap([
            'password' => 'Nueva-Password-456!',
        ]);

        $this->seed(SuperadminUserSeeder::class);

        $superadmin->refresh();

        $this->assertSame(
            $hashOriginal,
            $superadmin->password
        );

        $this->assertTrue(
            Hash::check(
                self::PASSWORD,
                $superadmin->password
            )
        );

        $this->assertFalse(
            Hash::check(
                'Nueva-Password-456!',
                $superadmin->password
            )
        );
    }

    public function test_existing_superadmin_is_not_overwritten(): void
    {
        $passwordExistente = 'Password-Existente-789!';

        $superadmin = User::factory()->create([
            'name' => 'Nombre existente',
            'apellido' => 'Apellido existente',
            'email' => self::EMAIL,
            'password' => $passwordExistente,
            'activo' => false,
            'email_verified_at' => null,
        ]);

        $superadmin->assignRole(
            RoleEnum::SUPERADMIN->value
        );

        $this->seed(SuperadminUserSeeder::class);

        $superadmin->refresh();

        $this->assertSame(
            'Nombre existente',
            $superadmin->name
        );

        $this->assertSame(
            'Apellido existente',
            $superadmin->apellido
        );

        $this->assertFalse($superadmin->activo);

        $this->assertNull(
            $superadmin->email_verified_at
        );

        $this->assertTrue(
            Hash::check(
                $passwordExistente,
                $superadmin->password
            )
        );

        $this->assertSame(
            [RoleEnum::SUPERADMIN->value],
            $superadmin->getRoleNames()->all()
        );

        $this->assertDatabaseCount('users', 1);
    }

    public function test_fails_when_email_belongs_to_non_superadmin_user(): void
    {
        $user = User::factory()->create([
            'email' => self::EMAIL,
        ]);

        $user->assignRole(
            RoleEnum::PACIENTE->value
        );

        try {
            $this->seed(SuperadminUserSeeder::class);

            $this->fail(
                'Se esperaba RuntimeException por colisión de email.'
            );
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'El email configurado para el superadministrador inicial ' .
                    'ya pertenece a un usuario que no posee exclusivamente ' .
                    'el rol superadmin. Revise ' .
                    'KINESIOSOFT_SUPERADMIN_EMAIL antes de ejecutar los seeders.',
                $exception->getMessage()
            );
        }

        $user->refresh();

        $this->assertTrue(
            $user->hasRole(RoleEnum::PACIENTE->value)
        );

        $this->assertFalse(
            $user->hasRole(RoleEnum::SUPERADMIN->value)
        );

        $this->assertSame(
            [RoleEnum::PACIENTE->value],
            $user->getRoleNames()->all()
        );

        $this->assertDatabaseCount('users', 1);
    }

    public function test_fails_when_existing_superadmin_has_an_additional_role(): void
    {
        $user = User::factory()->create([
            'email' => self::EMAIL,
        ]);

        $user->assignRole([
            RoleEnum::SUPERADMIN->value,
            RoleEnum::PACIENTE->value,
        ]);

        try {
            $this->seed(SuperadminUserSeeder::class);

            $this->fail(
                'Se esperaba RuntimeException por múltiples roles.'
            );
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'El email configurado para el superadministrador inicial ' .
                    'ya pertenece a un usuario que no posee exclusivamente ' .
                    'el rol superadmin. Revise ' .
                    'KINESIOSOFT_SUPERADMIN_EMAIL antes de ejecutar los seeders.',
                $exception->getMessage()
            );
        }

        $user->refresh();

        $this->assertTrue(
            $user->hasRole(RoleEnum::SUPERADMIN->value)
        );

        $this->assertTrue(
            $user->hasRole(RoleEnum::PACIENTE->value)
        );

        $this->assertCount(
            2,
            $user->getRoleNames()
        );
    }

    /**
     * @param  array<string, mixed>  $cambios
     */
    private function configurarBootstrap(
        array $cambios = []
    ): void {
        config([
            'kinesiosoft.bootstrap_superadmin' =>
            array_merge(
                [
                    'name' => 'Superadministrador',
                    'apellido' => 'Principal',
                    'email' => self::EMAIL,
                    'password' => self::PASSWORD,
                ],
                $cambios
            ),
        ]);
    }
}
