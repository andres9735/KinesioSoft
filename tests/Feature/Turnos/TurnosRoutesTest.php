<?php

namespace Tests\Feature\Turnos;

use App\Http\Controllers\DescargarDocumentoClinicoController;
use App\Http\Controllers\MisTurnosController;
use App\Http\Controllers\SolicitarTurnoController;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TurnosRoutesTest extends TestCase
{
    #[DataProvider('rutasPaciente')]
    public function test_patient_routes_have_exact_methods_uri_action_and_middleware(string $nombre, string $uri, array $metodos, array $accion): void
    {
        $ruta = Route::getRoutes()->getByName($nombre);
        $this->assertNotNull($ruta);
        $this->assertSame($uri, $ruta->uri());
        $this->assertSame($metodos, $ruta->methods());
        $this->assertSame(implode('@', $accion), $ruta->getActionName());
        $this->assertSame(['web', 'auth', 'verified', 'perfil.completo', 'perfil.paciente'], $ruta->gatherMiddleware());
        $this->assertCount(1, collect(Route::getRoutes())->filter(fn ($r) => $r->getName() === $nombre));
    }

    public static function rutasPaciente(): array
    {
        return [
            ['turnos.mis-turnos.index', 'turnos/mis-turnos', ['GET', 'HEAD'], [MisTurnosController::class, 'index']],
            ['turnos.solicitar.create', 'turnos/solicitar', ['GET', 'HEAD'], [SolicitarTurnoController::class, 'create']],
            ['turnos.solicitar.disponibilidad', 'turnos/solicitar/disponibilidad', ['GET', 'HEAD'], [SolicitarTurnoController::class, 'disponibilidad']],
            ['turnos.solicitar.store', 'turnos/solicitar', ['POST'], [SolicitarTurnoController::class, 'store']],
        ];
    }

    public function test_only_approved_patient_routes_exist_without_uc06_actions(): void
    {
        $this->assertTrue(Route::has('turnos.solicitar.create'));
        $paciente = collect(Route::getRoutes())->filter(fn ($ruta) => str_starts_with($ruta->uri(), 'turnos/')
            && ! str_starts_with($ruta->uri(), 'turnos/agenda'));
        $this->assertSame([
            'turnos.mis-turnos.index', 'turnos.solicitar.create', 'turnos.solicitar.disponibilidad', 'turnos.solicitar.store',
        ], $paciente->map(fn ($ruta) => $ruta->getName())->values()->all());
        $this->assertCount(1, $paciente->filter(fn ($ruta) => $ruta->uri() === 'turnos/solicitar' && in_array('GET', $ruta->methods(), true)));
    }

    public function test_private_document_download_route_remains_unchanged(): void
    {
        $ruta = Route::getRoutes()->getByName('documentos-clinicos.download');
        $this->assertSame('documentos-clinicos/{documento}/descargar', $ruta->uri());
        $this->assertSame(['GET', 'HEAD'], $ruta->methods());
        $this->assertSame(DescargarDocumentoClinicoController::class, $ruta->getActionName());
        $this->assertSame(['web', 'auth', 'verified', 'perfil.completo'], $ruta->gatherMiddleware());
    }
}
