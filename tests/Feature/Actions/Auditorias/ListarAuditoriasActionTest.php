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
        )->ejecutar(pagina: 2);

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
        )->ejecutar(pagina: 0);

        $this->assertSame(
            1,
            $resultado->currentPage()
        );
    }

    public function test_filters_audits_by_date(): void
    {
        Carbon::setTestNow(
            Carbon::parse('2026-08-10 10:00:00')
        );

        $auditoriaEsperada = Auditoria::create([
            'evento' => 'usuario_creado',
            'modulo' => 'Usuarios',
        ]);

        Carbon::setTestNow(
            Carbon::parse('2026-08-11 10:00:00')
        );

        Auditoria::create([
            'evento' => 'usuario_actualizado',
            'modulo' => 'Usuarios',
        ]);

        $resultado = app(
            ListarAuditoriasAction::class
        )->ejecutar([
            'fecha' => '2026-08-10',
        ]);

        $this->assertSame(1, $resultado->total());

        $this->assertSame(
            $auditoriaEsperada->id,
            $resultado->first()->id
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

        $this->assertSame(1, $resultado->total());

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

        $this->assertSame(1, $resultado->total());

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

        $this->assertSame(1, $porNombre->total());

        $this->assertSame(
            $auditoriaPorNombre->id,
            $porNombre->first()->id
        );

        $porEmail = app(
            ListarAuditoriasAction::class
        )->ejecutar([
            'actor' => 'carlos.admin',
        ]);

        $this->assertSame(1, $porEmail->total());

        $this->assertSame(
            $auditoriaPorEmail->id,
            $porEmail->first()->id
        );
    }

    public function test_combines_multiple_filters(): void
    {
        $auditoriaEsperada = Auditoria::create([
            'actor_nombre' => 'Ana Administradora',
            'actor_email' => 'ana.admin@example.com',
            'evento' => 'usuario_creado',
            'modulo' => 'Usuarios',
        ]);

        Auditoria::create([
            'actor_nombre' => 'Ana Administradora',
            'actor_email' => 'ana.admin@example.com',
            'evento' => 'usuario_actualizado',
            'modulo' => 'Usuarios',
        ]);

        Auditoria::create([
            'actor_nombre' => 'Carlos Administrador',
            'actor_email' => 'carlos@example.com',
            'evento' => 'usuario_creado',
            'modulo' => 'Usuarios',
        ]);

        $resultado = app(
            ListarAuditoriasAction::class
        )->ejecutar([
            'modulo' => 'Usuarios',
            'evento' => 'usuario_creado',
            'actor' => 'Ana',
        ]);

        $this->assertSame(1, $resultado->total());

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
            'fecha' => '   ',
            'modulo' => 'Usuarios',
            'evento' => '',
            'actor' => '  Ana  ',
        ]);

        $this->assertSame(11, $resultado->total());

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
            'fecha=',
            $urlSegundaPagina
        );

        $this->assertStringNotContainsString(
            'evento=',
            $urlSegundaPagina
        );
    }
}
