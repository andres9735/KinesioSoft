<?php

namespace Tests\Feature\Actions\Auditorias;

use App\Actions\Auditorias\ExportarAuditoriasPdfAction;
use App\Models\Auditoria;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportarAuditoriasPdfActionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_generates_pdf_with_all_filtered_records_without_pagination(): void
    {
        Carbon::setTestNow(
            Carbon::parse(
                '2026-08-14 16:45:30'
            )
        );

        for ($i = 1; $i <= 12; $i++) {
            Auditoria::create([
                'actor_nombre' =>
                'Administrador ' . $i,

                'actor_email' =>
                'admin' . $i . '@example.com',

                'evento' =>
                'usuario_actualizado',

                'modulo' =>
                'Usuarios',

                'ip_address' =>
                '127.0.0.1',
            ]);
        }

        Auditoria::create([
            'actor_nombre' =>
            'Administrador Cuenta',

            'actor_email' =>
            'cuenta@example.com',

            'evento' =>
            'cuenta_actualizada',

            'modulo' =>
            'Cuenta',
        ]);

        $exportador = User::factory()->create([
            'name' =>
            'Administrador',

            'apellido' =>
            'Principal',

            'email' =>
            'admin@kinesiosoft.com',
        ]);

        $resultado = app(
            ExportarAuditoriasPdfAction::class
        )->ejecutar(
            [
                'modulo' => 'Usuarios',
            ],
            $exportador
        );

        $this->assertSame(
            12,
            $resultado['cantidad']
        );

        $this->assertSame(
            'kinesiosoft_auditoria_20260814_164530.pdf',
            $resultado['nombre_archivo']
        );

        $this->assertStringStartsWith(
            '%PDF-',
            $resultado['contenido']
        );

        $this->assertGreaterThan(
            1000,
            strlen($resultado['contenido'])
        );
    }

    public function test_generates_pdf_using_combined_audit_filters(): void
    {
        Carbon::setTestNow(
            Carbon::parse(
                '2026-08-10 10:00:00'
            )
        );

        Auditoria::create([
            'actor_nombre' =>
            'Ana Administradora',

            'actor_email' =>
            'ana.admin@example.com',

            'evento' =>
            'usuario_creado',

            'modulo' =>
            'Usuarios',
        ]);

        Carbon::setTestNow(
            Carbon::parse(
                '2026-08-11 10:00:00'
            )
        );

        Auditoria::create([
            'actor_nombre' =>
            'Carlos Administrador',

            'actor_email' =>
            'carlos@example.com',

            'evento' =>
            'usuario_creado',

            'modulo' =>
            'Usuarios',
        ]);

        Carbon::setTestNow(
            Carbon::parse(
                '2026-08-15 10:00:00'
            )
        );

        Auditoria::create([
            'actor_nombre' =>
            'Ana Administradora',

            'actor_email' =>
            'ana.admin@example.com',

            'evento' =>
            'usuario_creado',

            'modulo' =>
            'Usuarios',
        ]);

        Carbon::setTestNow(
            Carbon::parse(
                '2026-08-14 17:00:00'
            )
        );

        $exportador = User::factory()->create([
            'name' =>
            'Administrador',

            'apellido' =>
            'Principal',

            'email' =>
            'admin@kinesiosoft.com',
        ]);

        $resultado = app(
            ExportarAuditoriasPdfAction::class
        )->ejecutar(
            [
                'fecha_desde' =>
                '2026-08-10',

                'fecha_hasta' =>
                '2026-08-12',

                'evento' =>
                'usuario_creado',

                'modulo' =>
                'Usuarios',

                'actor' =>
                'Ana',
            ],
            $exportador
        );

        $this->assertSame(
            1,
            $resultado['cantidad']
        );

        $this->assertStringStartsWith(
            '%PDF-',
            $resultado['contenido']
        );
    }
}
