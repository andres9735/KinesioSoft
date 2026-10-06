<?php

namespace Tests\Feature\Views;

use App\Enums\RoleEnum;
use App\Http\Middleware\EnsureUserProfileIsComplete;
use App\Models\BloqueoAgenda;
use App\Models\ConfiguracionAgenda;
use App\Models\FranjaHorariaAgenda;
use App\Models\Kinesiologa;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

abstract class AgendaViewTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::findOrCreate(RoleEnum::KINESIOLOGO->value, 'web');
        CarbonImmutable::setTestNow(CarbonImmutable::create(
            2026,
            10,
            6,
            12,
            0,
            0,
            config('app.timezone')
        ));
        $this->withoutMiddleware(EnsureUserProfileIsComplete::class);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    protected function actor(string $prefijo = 'actor'): User
    {
        $user = User::factory()->create([
            'email' => $prefijo.'_'.uniqid().'@example.com',
            'email_verified_at' => now(),
            'activo' => true,
        ]);
        $user->assignRole(RoleEnum::KINESIOLOGO->value);
        Kinesiologa::create(['user_id' => $user->id]);

        return $user->fresh('kinesiologa');
    }

    protected function configuracion(
        Kinesiologa $kinesiologa,
        string $desde,
        ?string $hasta = null,
        ?string $anuladoAt = null,
        int $duracion = 45,
        bool $conFranja = true
    ): ConfiguracionAgenda {
        $configuracion = ConfiguracionAgenda::factory()->for($kinesiologa)->create([
            'vigencia_desde' => $desde,
            'vigencia_hasta' => $hasta,
            'duracion_turno_minutos' => $duracion,
            'anulado_at' => $anuladoAt,
            'motivo_anulacion' => $anuladoAt === null ? null : 'Cambio de planificación',
        ]);

        if ($conFranja) {
            $this->franja($configuracion, 1, '08:00:00', '12:00:00');
        }

        return $configuracion;
    }

    protected function franja(
        ConfiguracionAgenda $configuracion,
        int $dia,
        string $inicio,
        string $fin
    ): FranjaHorariaAgenda {
        return FranjaHorariaAgenda::factory()
            ->for($configuracion, 'configuracionAgenda')
            ->create([
                'dia_semana' => $dia,
                'hora_inicio' => $inicio,
                'hora_fin' => $fin,
            ]);
    }

    protected function bloqueo(
        Kinesiologa $kinesiologa,
        string $inicio,
        string $fin,
        string $motivo,
        ?string $anuladoAt = null
    ): BloqueoAgenda {
        return BloqueoAgenda::factory()->for($kinesiologa)->create([
            'inicio_at' => $inicio,
            'fin_at' => $fin,
            'motivo' => $motivo,
            'anulado_at' => $anuladoAt,
            'motivo_anulacion' => $anuladoAt === null ? null : 'Corrección administrativa',
        ]);
    }
}
