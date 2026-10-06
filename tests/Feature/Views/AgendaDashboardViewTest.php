<?php

namespace Tests\Feature\Views;

use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\DataProvider;

class AgendaDashboardViewTest extends AgendaViewTestCase
{
    public function test_primer_uso_muestra_el_estado_inicial_sin_configuracion_ficticia(): void
    {
        $actor = $this->actor();

        $this->actingAs($actor)
            ->get(route('turnos.agenda.index'))
            ->assertOk()
            ->assertSee('Configurar agenda de atención')
            ->assertSee('Todavía no configuraste tu agenda')
            ->assertSee('Crear primera configuración')
            ->assertDontSee('Matriz semanal vigente');
    }

    public function test_hueco_actual_mantiene_las_futuras_separadas(): void
    {
        $actor = $this->actor();
        $this->configuracion($actor->kinesiologa, '2026-09-01', '2026-10-05');
        $this->configuracion($actor->kinesiologa, '2026-10-10', null, duracion: 60);

        $this->actingAs($actor)
            ->get(route('turnos.agenda.index'))
            ->assertOk()
            ->assertSee('Sin configuración vigente')
            ->assertSee('Configuraciones futuras')
            ->assertSee('Futura')
            ->assertSee('Próxima')
            ->assertSee('10/10/2026')
            ->assertDontSee('Matriz semanal vigente');
    }

    public function test_vigente_renderiza_matriz_completa_ordenada_y_solo_de_lectura(): void
    {
        $actor = $this->actor();
        $vigente = $this->configuracion(
            $actor->kinesiologa,
            '2026-10-01',
            null,
            duracion: 45,
            conFranja: false
        );
        $this->franja($vigente, 1, '14:00:00', '18:00:00');
        $this->franja($vigente, 1, '08:00:00', '12:00:00');
        $this->franja($vigente, 3, '09:00:00', '13:00:00');
        $vigente->load('franjasHorarias');

        $matriz = Blade::render(
            '<x-agenda.matriz-semanal-lectura :franjas="$franjas" id="prueba" />',
            ['franjas' => $vigente->franjasHorarias]
        );

        $response = $this->actingAs($actor)->get(route('turnos.agenda.index'));

        $response
            ->assertOk()
            ->assertSee('Vigente')
            ->assertSee('Sin fecha de fin')
            ->assertSeeInOrder(['Lunes', '08:00', '12:00', '14:00', '18:00', 'Martes', 'Sin atención', 'Miércoles'])
            ->assertSeeInOrder(['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'])
            ->assertSee('Consultar')
            ->assertDontSee('>Editar<', false)
            ->assertDontSee('>Anular<', false)
            ->assertDontSee('Agregar franja')
            ->assertDontSee('Quitar franja');
        $this->assertStringNotContainsString('<input', $matriz);
        $this->assertStringNotContainsString('x-data', $matriz);
    }

    public function test_futuras_se_muestran_en_orden_con_proxima_y_acciones_autorizadas(): void
    {
        $actor = $this->actor();
        $this->configuracion($actor->kinesiologa, '2026-10-20', null, duracion: 60);
        $this->configuracion($actor->kinesiologa, '2026-10-10', '2026-10-19', duracion: 30);

        $response = $this->actingAs($actor)->get(route('turnos.agenda.index'));

        $response
            ->assertOk()
            ->assertSeeInOrder(['10/10/2026', '20/10/2026'])
            ->assertSee('Futura')
            ->assertSee('Próxima')
            ->assertSee('Editar')
            ->assertSee('Anular');
    }

    public function test_sin_futuras_y_los_dos_estados_vacios_de_bloqueos_son_explicitos(): void
    {
        $actor = $this->actor();
        $this->configuracion($actor->kinesiologa, '2026-10-01');

        $this->actingAs($actor)
            ->get(route('turnos.agenda.index'))
            ->assertOk()
            ->assertSee('No hay configuraciones futuras programadas')
            ->assertSee('No hay bloqueos registrados')
            ->assertSee('Registrar bloqueo');

        $this->bloqueo(
            $actor->kinesiologa,
            '2026-10-05 09:00:00',
            '2026-10-05 10:00:00',
            'Bloqueo pasado'
        );

        $this->actingAs($actor)
            ->get(route('turnos.agenda.index', ['filtro' => 'proximos']))
            ->assertOk()
            ->assertSee('No hay bloqueos próximos')
            ->assertSee('Ver todos')
            ->assertDontSee('No hay bloqueos registrados');
    }

    #[DataProvider('filtros')]
    public function test_cada_filtro_renderiza_su_label_seleccion_y_estado(
        string $filtro,
        string $label,
        string $inicio,
        string $fin,
        ?string $anuladoAt,
        string $estado,
        bool $puedeAnular
    ): void {
        $actor = $this->actor();
        $otra = $this->actor('otra');
        $this->bloqueo($actor->kinesiologa, $inicio, $fin, 'Motivo visible '.$filtro, $anuladoAt);
        $this->bloqueo($otra->kinesiologa, $inicio, $fin, 'Motivo ajeno '.$filtro, $anuladoAt);

        $response = $this->actingAs($actor)->get(route('turnos.agenda.index', ['filtro' => $filtro]));

        $response
            ->assertOk()
            ->assertSee($label)
            ->assertSee('value="'.$filtro.'" selected', false)
            ->assertSee('Motivo visible '.$filtro)
            ->assertDontSee('Motivo ajeno '.$filtro)
            ->assertSee($estado)
            ->assertDontSee('Activo')
            ->assertDontSee('Inactivo');

        if ($puedeAnular) {
            $response->assertSee('>Anular<', false);
        } else {
            $response->assertDontSee('>Anular<', false);
        }
    }

    public static function filtros(): array
    {
        return [
            'próximos' => ['proximos', 'Próximos', '2026-10-07 09:00:00', '2026-10-07 10:00:00', null, 'Próximo', true],
            'en curso' => ['en_curso', 'En curso', '2026-10-06 11:00:00', '2026-10-06 13:00:00', null, 'En curso', true],
            'pasados' => ['pasados', 'Pasados', '2026-10-05 09:00:00', '2026-10-05 10:00:00', null, 'Pasado', true],
            'anulados' => ['anulados', 'Anulados', '2026-10-07 09:00:00', '2026-10-07 10:00:00', '2026-10-05 08:00:00', 'Anulado', false],
            'todos' => ['todos', 'Todos', '2026-10-07 09:00:00', '2026-10-07 10:00:00', null, 'Próximo', true],
        ];
    }

    public function test_bloqueos_paginan_diez_preservan_filtro_y_aislan_otra_kinesiologa(): void
    {
        $actor = $this->actor();
        $otra = $this->actor('otra');

        foreach (range(1, 11) as $indice) {
            $this->bloqueo(
                $actor->kinesiologa,
                sprintf('2026-10-%02d 09:00:00', 6 + $indice),
                sprintf('2026-10-%02d 10:00:00', 6 + $indice),
                'Bloqueo propio '.$indice
            );
        }
        $this->bloqueo($otra->kinesiologa, '2026-10-20 09:00:00', '2026-10-20 10:00:00', 'Bloqueo ajeno');

        $response = $this->actingAs($actor)->get(route('turnos.agenda.index', ['filtro' => 'proximos']));
        $paginador = $response->viewData('bloqueos');

        $response
            ->assertOk()
            ->assertSee('filtro=proximos', false)
            ->assertSee('bloqueos_page=2', false)
            ->assertDontSee('Bloqueo ajeno');
        $this->assertCount(10, $paginador->items());
        $this->assertSame(11, $paginador->total());
    }
}
