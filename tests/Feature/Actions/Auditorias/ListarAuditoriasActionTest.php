<?php

namespace Tests\Feature\Actions\Auditorias;

use App\Actions\Auditorias\ListarAuditoriasAction;
use App\Models\Auditoria;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListarAuditoriasActionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_returns_first_page_with_ten_most_recent_audits(): void
    {
        $auditorias = collect();

        for ($i = 1; $i <= 12; $i++) {
            $auditorias->push(
                Auditoria::create([
                    'evento' => 'evento_' . $i,
                    'modulo' => 'Pruebas',
                ])
            );
        }

        $resultado = app(
            ListarAuditoriasAction::class
        )->ejecutar();

        $this->assertSame(12, $resultado->total());
        $this->assertSame(10, $resultado->perPage());
        $this->assertSame(1, $resultado->currentPage());
        $this->assertSame(2, $resultado->lastPage());
        $this->assertCount(10, $resultado->items());

        $idsEsperados = $auditorias
            ->reverse()
            ->take(10)
            ->pluck('id')
            ->values()
            ->all();

        $idsObtenidos = $resultado
            ->getCollection()
            ->pluck('id')
            ->values()
            ->all();

        $this->assertSame(
            $idsEsperados,
            $idsObtenidos
        );
    }

    public function test_returns_second_page_with_remaining_audits(): void
    {
        $auditorias = collect();

        for ($i = 1; $i <= 12; $i++) {
            $auditorias->push(
                Auditoria::create([
                    'evento' => 'evento_' . $i,
                    'modulo' => 'Pruebas',
                ])
            );
        }

        $resultado = app(
            ListarAuditoriasAction::class
        )->ejecutar(
            pagina: 2
        );

        $this->assertSame(12, $resultado->total());
        $this->assertSame(2, $resultado->currentPage());
        $this->assertCount(2, $resultado->items());

        $idsEsperados = $auditorias
            ->reverse()
            ->slice(10)
            ->pluck('id')
            ->values()
            ->all();

        $idsObtenidos = $resultado
            ->getCollection()
            ->pluck('id')
            ->values()
            ->all();

        $this->assertSame(
            $idsEsperados,
            $idsObtenidos
        );
    }

    public function test_normalizes_invalid_page_to_first_page(): void
    {
        Auditoria::create([
            'evento' => 'evento_prueba',
            'modulo' => 'Pruebas',
        ]);

        $resultado = app(
            ListarAuditoriasAction::class
        )->ejecutar(
            pagina: 0
        );

        $this->assertSame(
            1,
            $resultado->currentPage()
        );
    }

    public function test_filters_audits_from_start_date_inclusively(): void
    {
        Carbon::setTestNow(
            Carbon::parse('2026-08-09 10:00:00')
        );

        Auditoria::create([
            'evento' => 'evento_anterior',
            'modulo' => 'Pruebas',
        ]);

        Carbon::setTestNow(
            Carbon::parse('2026-08-10 10:00:00')
        );

        $auditoriaDesde = Auditoria::create([
            'evento' => 'evento_desde',
            'modulo' => 'Pruebas',
        ]);

        Carbon::setTestNow(
            Carbon::parse('2026-08-11 10:00:00')
        );

        $auditoriaPosterior = Auditoria::create([
            'evento' => 'evento_posterior',
            'modulo' => 'Pruebas',
        ]);

        $resultado = app(
            ListarAuditoriasAction::class
        )->ejecutar([
            'fecha_desde' => '2026-08-10',
        ]);

        $this->assertSame(
            2,
            $resultado->total()
        );

        $this->assertSame(
            [
                $auditoriaPosterior->id,
                $auditoriaDesde->id,
            ],
            $resultado
                ->getCollection()
                ->pluck('id')
                ->values()
                ->all()
        );
    }

    public function test_filters_audits_until_end_date_inclusively(): void
    {
        Carbon::setTestNow(
            Carbon::parse('2026-08-09 10:00:00')
        );

        $auditoriaAnterior = Auditoria::create([
            'evento' => 'evento_anterior',
            'modulo' => 'Pruebas',
        ]);

        Carbon::setTestNow(
            Carbon::parse('2026-08-10 18:30:00')
        );

        $auditoriaHasta = Auditoria::create([
            'evento' => 'evento_hasta',
            'modulo' => 'Pruebas',
        ]);

        Carbon::setTestNow(
            Carbon::parse('2026-08-11 10:00:00')
        );

        Auditoria::create([
            'evento' => 'evento_posterior',
            'modulo' => 'Pruebas',
        ]);

        $resultado = app(
            ListarAuditoriasAction::class
        )->ejecutar([
            'fecha_hasta' => '2026-08-10',
        ]);

        $this->assertSame(
            2,
            $resultado->total()
        );

        $this->assertSame(
            [
                $auditoriaHasta->id,
                $auditoriaAnterior->id,
            ],
            $resultado
                ->getCollection()
                ->pluck('id')
                ->values()
                ->all()
        );
    }

    public function test_filters_audits_by_inclusive_date_range(): void
    {
        Carbon::setTestNow(
            Carbon::parse('2026-08-09 10:00:00')
        );

        Auditoria::create([
            'evento' => 'fuera_antes',
            'modulo' => 'Pruebas',
        ]);

        Carbon::setTestNow(
            Carbon::parse('2026-08-10 08:00:00')
        );

        $auditoriaInicio = Auditoria::create([
            'evento' => 'inicio',
            'modulo' => 'Pruebas',
        ]);

        Carbon::setTestNow(
            Carbon::parse('2026-08-11 12:00:00')
        );

        $auditoriaIntermedia = Auditoria::create([
            'evento' => 'intermedio',
            'modulo' => 'Pruebas',
        ]);

        Carbon::setTestNow(
            Carbon::parse('2026-08-12 23:00:00')
        );

        $auditoriaFin = Auditoria::create([
            'evento' => 'fin',
            'modulo' => 'Pruebas',
        ]);

        Carbon::setTestNow(
            Carbon::parse('2026-08-13 10:00:00')
        );

        Auditoria::create([
            'evento' => 'fuera_despues',
            'modulo' => 'Pruebas',
        ]);

        $resultado = app(
            ListarAuditoriasAction::class
        )->ejecutar([
            'fecha_desde' => '2026-08-10',
            'fecha_hasta' => '2026-08-12',
        ]);

        $this->assertSame(
            3,
            $resultado->total()
        );

        $this->assertSame(
            [
                $auditoriaFin->id,
                $auditoriaIntermedia->id,
                $auditoriaInicio->id,
            ],
            $resultado
                ->getCollection()
                ->pluck('id')
                ->values()
                ->all()
        );
    }

    public function test_filters_audits_by_module(): void
    {
        $auditoriaEsperada = Auditoria::create([
            'evento' => 'inicio_sesion',
            'modulo' => 'Sistema / Acceso',
        ]);

        Auditoria::create([
            'evento' => 'usuario_creado',
            'modulo' => 'Usuarios',
        ]);

        $resultado = app(
            ListarAuditoriasAction::class
        )->ejecutar([
            'modulo' => 'Sistema / Acceso',
        ]);

        $this->assertSame(
            1,
            $resultado->total()
        );

        $this->assertSame(
            $auditoriaEsperada->id,
            $resultado->first()->id
        );
    }

    public function test_filters_audits_by_event(): void
    {
        $auditoriaEsperada = Auditoria::create([
            'evento' => 'email_verificado',
            'modulo' => 'Cuenta',
        ]);

        Auditoria::create([
            'evento' => 'cuenta_actualizada',
            'modulo' => 'Cuenta',
        ]);

        $resultado = app(
            ListarAuditoriasAction::class
        )->ejecutar([
            'evento' => 'email_verificado',
        ]);

        $this->assertSame(
            1,
            $resultado->total()
        );

        $this->assertSame(
            $auditoriaEsperada->id,
            $resultado->first()->id
        );
    }

    public function test_filters_audits_by_actor_name_or_email(): void
    {
        $auditoriaPorNombre = Auditoria::create([
            'actor_nombre' => 'Ana Paciente',
            'actor_email' => 'ana@example.com',
            'evento' => 'cuenta_actualizada',
            'modulo' => 'Cuenta',
        ]);

        $auditoriaPorEmail = Auditoria::create([
            'actor_nombre' => 'Carlos Administrador',
            'actor_email' => 'carlos.admin@example.com',
            'evento' => 'usuario_creado',
            'modulo' => 'Usuarios',
        ]);

        Auditoria::create([
            'actor_nombre' => 'Lucía Kinesióloga',
            'actor_email' => 'lucia@example.com',
            'evento' => 'perfil_kinesiologa_actualizado',
            'modulo' => 'Perfiles',
        ]);

        $porNombre = app(
            ListarAuditoriasAction::class
        )->ejecutar([
            'actor' => 'ana paciente',
        ]);

        $this->assertSame(
            1,
            $porNombre->total()
        );

        $this->assertSame(
            $auditoriaPorNombre->id,
            $porNombre->first()->id
        );

        $porEmail = app(
            ListarAuditoriasAction::class
        )->ejecutar([
            'actor' => 'carlos.admin',
        ]);

        $this->assertSame(
            1,
            $porEmail->total()
        );

        $this->assertSame(
            $auditoriaPorEmail->id,
            $porEmail->first()->id
        );
    }

    public function test_combines_multiple_filters(): void
    {
        Carbon::setTestNow(
            Carbon::parse('2026-08-10 10:00:00')
        );

        $auditoriaEsperada = Auditoria::create([
            'actor_nombre' => 'Ana Administradora',
            'actor_email' => 'ana.admin@example.com',
            'evento' => 'usuario_creado',
            'modulo' => 'Usuarios',
        ]);

        Carbon::setTestNow(
            Carbon::parse('2026-08-11 10:00:00')
        );

        Auditoria::create([
            'actor_nombre' => 'Ana Administradora',
            'actor_email' => 'ana.admin@example.com',
            'evento' => 'usuario_actualizado',
            'modulo' => 'Usuarios',
        ]);

        Carbon::setTestNow(
            Carbon::parse('2026-08-12 10:00:00')
        );

        Auditoria::create([
            'actor_nombre' => 'Carlos Administrador',
            'actor_email' => 'carlos@example.com',
            'evento' => 'usuario_creado',
            'modulo' => 'Usuarios',
        ]);

        Carbon::setTestNow(
            Carbon::parse('2026-08-13 10:00:00')
        );

        Auditoria::create([
            'actor_nombre' => 'Ana Administradora',
            'actor_email' => 'ana.admin@example.com',
            'evento' => 'usuario_creado',
            'modulo' => 'Usuarios',
        ]);

        $resultado = app(
            ListarAuditoriasAction::class
        )->ejecutar([
            'fecha_desde' => '2026-08-10',
            'fecha_hasta' => '2026-08-12',
            'modulo' => 'Usuarios',
            'evento' => 'usuario_creado',
            'actor' => 'Ana',
        ]);

        $this->assertSame(
            1,
            $resultado->total()
        );

        $this->assertSame(
            $auditoriaEsperada->id,
            $resultado->first()->id
        );
    }

    public function test_ignores_empty_filters_and_preserves_active_filters_in_pagination(): void
    {
        for ($i = 1; $i <= 11; $i++) {
            Auditoria::create([
                'actor_nombre' => 'Ana Administradora',
                'actor_email' => 'ana@example.com',
                'evento' => 'usuario_creado',
                'modulo' => 'Usuarios',
            ]);
        }

        $resultado = app(
            ListarAuditoriasAction::class
        )->ejecutar([
            'fecha_desde' => '   ',
            'fecha_hasta' => '',
            'modulo' => 'Usuarios',
            'evento' => '',
            'actor' => '  Ana  ',
        ]);

        $this->assertSame(
            11,
            $resultado->total()
        );

        $urlSegundaPagina = $resultado->url(2);

        $this->assertStringContainsString(
            'modulo=Usuarios',
            $urlSegundaPagina
        );

        $this->assertStringContainsString(
            'actor=Ana',
            $urlSegundaPagina
        );

        $this->assertStringNotContainsString(
            'fecha_desde=',
            $urlSegundaPagina
        );

        $this->assertStringNotContainsString(
            'fecha_hasta=',
            $urlSegundaPagina
        );

        $this->assertStringNotContainsString(
            'evento=',
            $urlSegundaPagina
        );
    }

    public function test_preserves_date_range_in_pagination(): void
    {
        Carbon::setTestNow(
            Carbon::parse('2026-08-10 10:00:00')
        );

        for ($i = 1; $i <= 11; $i++) {
            Auditoria::create([
                'evento' => 'usuario_creado',
                'modulo' => 'Usuarios',
            ]);
        }

        $resultado = app(
            ListarAuditoriasAction::class
        )->ejecutar([
            'fecha_desde' => '2026-08-01',
            'fecha_hasta' => '2026-08-31',
        ]);

        $urlSegundaPagina = $resultado->url(2);

        $this->assertStringContainsString(
            'fecha_desde=2026-08-01',
            $urlSegundaPagina
        );

        $this->assertStringContainsString(
            'fecha_hasta=2026-08-31',
            $urlSegundaPagina
        );
    }
}
