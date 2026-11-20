<?php

namespace App\Http\Controllers;

use App\Actions\HistoriaClinica\AbrirHistoriaClinicaAction;
use App\Exceptions\HistoriaClinica\RecorridoAperturaModificadoException;
use App\Exceptions\Turnos\RecursosTurnoModificadosException;
use App\Http\Requests\AbrirHistoriaClinicaRequest;
use App\Models\Turno;
use App\Services\HistoriaClinica\ConsultarExpedienteDesdeTurnoService;
use App\Services\HistoriaClinica\ContextoAperturaHistoriaClinica;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class AperturaHistoriaClinicaController extends Controller
{
    public function create(Request $request, string $turno, ConsultarExpedienteDesdeTurnoService $consulta, ContextoAperturaHistoriaClinica $contextos): Response|RedirectResponse
    {
        Gate::forUser($request->user())->authorize('abrirHistoriaClinica', [Turno::class, (int) $turno]);
        $datos = $consulta->consultar($request->user(), (int) $turno);
        if ($datos['recorrido'] !== 'apertura_a') {
            return $this->volver($turno);
        }
        if (! Schema::hasTable('versiones_datos_clinicos_base')) {
            abort(503, 'La apertura todavía no está disponible en esta instalación.');
        }
        $datos['especialidades'] = $datos['turno']->kinesiologa->especialidades()->where('activo', true)->orderBy('nombre')->get();
        $datos['contexto'] = $contextos->emitir($request->user(), $datos['turno']);

        return response()->view('historia-clinica.apertura', $datos)->header('Cache-Control', 'no-store, private');
    }

    public function store(AbrirHistoriaClinicaRequest $request, string $turno, AbrirHistoriaClinicaAction $abrir): RedirectResponse
    {
        try {
            $abrir->ejecutar($request->user(), (int) $turno, $request->validated('contexto'), $request->datosApertura());
        } catch (RecorridoAperturaModificadoException|RecursosTurnoModificadosException $e) {
            return $this->volver($turno)->with('aviso_apertura', $e->getMessage());
        } catch (ValidationException $e) {
            if (isset($e->errors()['contexto'])) {
                // No repoblar otro paciente/contacto con los datos del formulario anterior.
                return $this->volver($turno)->with('aviso_apertura', $e->errors()['contexto'][0]);
            }
            throw $e;
        } catch (QueryException $e) {
            // No incluir consultas ni contenido clínico en mensajes o logs.
            return back()->withInput()->withErrors(['guardado' => 'No se pudo guardar la apertura. La operación se revirtió; podés reintentar.']);
        }

        return $this->volver($turno)->with('aviso_apertura', 'Historia clínica activa y primera versión profesional guardadas.');
    }

    private function volver(string $turno): RedirectResponse
    {
        return redirect()->route('historia-clinica.desde-turno', ['turno' => $turno]);
    }
}
