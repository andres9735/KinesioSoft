<?php

namespace Tests\Feature\Actions\Auditorias;

use App\Actions\Auditorias\ListarAuditoriasAction;
use App\Models\Auditoria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListarAuditoriasActionTest extends TestCase
{
    use RefreshDatabase;

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
}
