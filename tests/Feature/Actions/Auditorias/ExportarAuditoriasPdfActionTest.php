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

    public function test_audits_successful_pdf_export_with_filters_count_and_filename(): void
    {
        Carbon::setTestNow(
            Carbon::parse(
                '2026-08-16 11:30:45'
            )
        );

        Auditoria::create([
            'actor_nombre' =>
            'Ana Administradora',

            'actor_email' =>
            'ana@example.com',

            'evento' =>
            'usuario_actualizado',

            'modulo' =>
            'Usuarios',
        ]);

        Auditoria::create([
            'actor_nombre' =>
            'Carlos Administrador',

            'actor_email' =>
            'carlos@example.com',

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
            1,
            $resultado['cantidad']
        );

        $auditoriaExportacion = Auditoria::query()
            ->where(
                'evento',
                'auditoria_exportada_pdf'
            )
            ->sole();

        $this->assertSame(
            'Auditoría',
            $auditoriaExportacion->modulo
        );

        $this->assertSame(
            $exportador->id,
            $auditoriaExportacion->actor_user_id
        );

        $this->assertSame(
            'Administrador Principal',
            $auditoriaExportacion->actor_nombre
        );

        $this->assertSame(
            'admin@kinesiosoft.com',
            $auditoriaExportacion->actor_email
        );

        $this->assertNull(
            $auditoriaExportacion->auditable_type
        );

        $this->assertNull(
            $auditoriaExportacion->auditable_id
        );

        $this->assertNull(
            $auditoriaExportacion->valores_anteriores
        );

        $this->assertNull(
            $auditoriaExportacion->valores_nuevos
        );

        $this->assertSame(
            [
                'filtros' => [
                    'modulo' => 'Usuarios',
                ],
                'cantidad_registros' => 1,
                'nombre_archivo' =>
                'kinesiosoft_auditoria_20260816_113045.pdf',
            ],
            $auditoriaExportacion->metadata
        );
    }

    public function test_export_does_not_include_its_own_audit_event(): void
    {
        Carbon::setTestNow(
            Carbon::parse(
                '2026-08-16 11:45:00'
            )
        );

        Auditoria::create([
            'actor_nombre' =>
            'Administrador Existente',

            'actor_email' =>
            'existente@example.com',

            'evento' =>
            'usuario_actualizado',

            'modulo' =>
            'Usuarios',
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
            [],
            $exportador
        );

        /*
         * Al momento de generar el PDF existía sólo
         * un registro. Después de la generación queda
         * además registrada la propia exportación.
         */
        $this->assertSame(
            1,
            $resultado['cantidad']
        );

        $this->assertDatabaseCount(
            'auditorias',
            2
        );

        $this->assertDatabaseHas(
            'auditorias',
            [
                'evento' =>
                'auditoria_exportada_pdf',

                'modulo' =>
                'Auditoría',

                'actor_user_id' =>
                $exportador->id,
            ]
        );
    }

    public function test_does_not_generate_or_audit_export_when_filters_have_no_results(): void
    {
        Auditoria::create([
            'actor_nombre' =>
            'Administrador Existente',

            'actor_email' =>
            'existente@example.com',

            'evento' =>
            'usuario_actualizado',

            'modulo' =>
            'Usuarios',
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
                'modulo' =>
                'Modulo inexistente',
            ],
            $exportador
        );

        $this->assertNull(
            $resultado
        );

        $this->assertDatabaseCount(
            'auditorias',
            1
        );

        $this->assertDatabaseMissing(
            'auditorias',
            [
                'evento' =>
                'auditoria_exportada_pdf',
            ]
        );
    }
}
