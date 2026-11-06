<?php

namespace Tests\Feature\Services\Turnos;

use App\Enums\TipoTurnoEnum;
use App\Models\EnlaceAsistenciaTurno;
use App\Models\Turno;
use App\Services\Turnos\AccesoEnlaceAsistencia;
use App\Services\Turnos\EmitirEnlaceAsistenciaTurnoService;
use App\Services\Turnos\ValidarEnlaceAsistenciaTurnoService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CreaPerfilesUc07;
use Tests\TestCase;

class ValidarEnlaceAsistenciaTurnoServiceTest extends TestCase
{
    use CreaPerfilesUc07;
    use RefreshDatabase;

    private Turno $turno;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.timezone' => 'America/Argentina/Buenos_Aires', 'app.url' => 'https://consultorio.example.test']);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-05 07:00:00', config('app.timezone')));
        $this->turno = Turno::factory()->for($this->profesionalUc07())->for($this->pacienteUc07())->create([
            'tipo' => TipoTurnoEnum::SEGUIMIENTO, 'inicio_at' => '2026-11-08 10:00:00', 'fin_at' => '2026-11-08 10:30:00',
            'motivo_solicitud' => 'CLINICO-EXCLUIDO',
        ])->fresh();
        $resultado = app(EmitirEnlaceAsistenciaTurnoService::class)->emitir($this->turno->id);
        parse_str(parse_url($resultado->urlParaCorreo(), PHP_URL_QUERY), $query);
        $this->token = $query['token'];
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_consulta_repetida_sin_sesion_no_escribe_y_devuelve_solo_cita_minima(): void
    {
        $this->assertGuest();
        $antes = $this->turno->getAttributes();
        $enlaces = DB::table('enlaces_asistencia_turno')->get()->toJson();
        $escrituras = [];
        DB::listen(function ($query) use (&$escrituras): void {
            if (preg_match('/^\s*(insert|update|delete|replace|alter|create|drop)/i', $query->sql)) {
                $escrituras[] = $query->sql;
            }
        });
        for ($i = 0; $i < 3; $i++) {
            $acceso = $this->validar();
            $this->assertSame(AccesoEnlaceAsistencia::VALIDO, $acceso->estado);
            $this->assertTrue($acceso->puedeConfirmar);
            $this->assertTrue($acceso->puedeCancelar);
        }
        $this->assertSame(['tipo', 'fecha', 'horario', 'kinesiologa', 'estado'], array_keys($acceso->cita));
        $this->assertStringNotContainsString($this->token, json_encode($acceso));
        $this->assertStringNotContainsString('CLINICO-EXCLUIDO', json_encode($acceso));
        $this->assertSame([], $escrituras);
        $this->assertSame($antes, $this->turno->fresh()->getAttributes());
        $this->assertSame($enlaces, DB::table('enlaces_asistencia_turno')->get()->toJson());
        $this->assertDatabaseCount('auditorias', 1);
    }

    #[DataProvider('tokensInvalidos')]
    public function test_token_invalido_no_revela_cita(string $token): void
    {
        $acceso = app(ValidarEnlaceAsistenciaTurnoService::class)->validar($token);
        $this->assertSame(AccesoEnlaceAsistencia::INVALIDO, $acceso->estado);
        $this->assertSame([], $acceso->cita);
        $this->assertFalse($acceso->puedeConfirmar);
        $this->assertFalse($acceso->puedeCancelar);
    }

    public static function tokensInvalidos(): array
    {
        return [[''], ['1'], [str_repeat('a', 64)], [str_repeat('A', 64)], [str_repeat('x', 2000)], ['<script>']];
    }

    public function test_alterar_token_no_autoriza(): void
    {
        $token = $this->token;
        $token[0] = $token[0] === 'a' ? 'b' : 'a';
        $this->assertSame(AccesoEnlaceAsistencia::INVALIDO, app(ValidarEnlaceAsistenciaTurnoService::class)->validar($token)->estado);
    }

    #[DataProvider('limites')]
    public function test_estados_y_limites_temporales_independientes_del_acceso(string $estado, string $ahora, string $esperado, bool $confirmar, bool $cancelar): void
    {
        DB::table('turnos')->where('id', $this->turno->id)->update(['estado' => $estado]);
        CarbonImmutable::setTestNow(CarbonImmutable::parse($ahora, config('app.timezone')));
        $acceso = $this->validar();
        $this->assertSame($esperado, $acceso->estado);
        $this->assertSame($confirmar, $acceso->puedeConfirmar);
        $this->assertSame($cancelar, $acceso->puedeCancelar);
        if ($esperado !== AccesoEnlaceAsistencia::VALIDO) {
            $this->assertSame([], $acceso->cita);
        }
    }

    public static function limites(): array
    {
        return [
            ['PENDIENTE', '2026-11-07 21:59:59.999999', 'valido', true, true],
            ['PENDIENTE', '2026-11-07 22:00:00', 'valido', true, true],
            ['PENDIENTE', '2026-11-07 22:00:00.000001', 'valido', true, false],
            ['CONFIRMADO', '2026-11-07 22:00:00', 'valido', false, true],
            ['CONFIRMADO', '2026-11-07 22:00:00.000001', 'valido', false, false],
            ['PENDIENTE', '2026-11-08 09:59:59.999999', 'valido', true, false],
            ['PENDIENTE', '2026-11-08 10:00:00', 'vencido', false, false],
            ['CONFIRMADO', '2026-11-08 10:00:01', 'vencido', false, false],
            ['PENDIENTE', '2026-11-05 06:59:59', 'invalido', false, false],
            ['CANCELADO', '2026-11-07 10:00:00', 'estado_incompatible', false, false],
            ['EN_ATENCION', '2026-11-07 10:00:00', 'estado_incompatible', false, false],
            ['ATENDIDO', '2026-11-07 10:00:00', 'estado_incompatible', false, false],
            ['AUSENTE', '2026-11-07 10:00:00', 'estado_incompatible', false, false],
        ];
    }

    public function test_revocado_rechaza_sin_datos(): void
    {
        EnlaceAsistenciaTurno::sole()->forceFill(['revocado_at' => CarbonImmutable::now(config('app.timezone'))])->save();
        $acceso = $this->validar();
        $this->assertSame('revocado', $acceso->estado);
        $this->assertSame([], $acceso->cita);
    }

    public function test_aba_y_restauracion_de_estado_no_rehabilitan(): void
    {
        foreach (['2026-11-09 10:00:00', '2026-11-08 10:00:00'] as $inicio) {
            DB::table('turnos')->where('id', $this->turno->id)->update(['inicio_at' => $inicio]);
            $this->assertSame('programacion_modificada', $this->validar()->estado);
        }
        $this->assertSame([], $this->validar()->cita);
        DB::table('turnos')->where('id', $this->turno->id)->update(['estado' => 'CANCELADO']);
        DB::table('turnos')->where('id', $this->turno->id)->update(['estado' => 'PENDIENTE']);
        $this->assertSame('programacion_modificada', $this->validar()->estado);
    }

    public function test_propiedad_ajena_o_profesional_distinta_no_exponen_datos(): void
    {
        DB::table('turnos')->where('id', $this->turno->id)->update([
            'paciente_id' => $this->pacienteUc07()->id, 'kinesiologa_id' => $this->profesionalUc07()->id,
        ]);
        $acceso = $this->validar();
        $this->assertSame('programacion_modificada', $acceso->estado);
        $this->assertSame([], $acceso->cita);
    }

    private function validar(): AccesoEnlaceAsistencia
    {
        return app(ValidarEnlaceAsistenciaTurnoService::class)->validar($this->token);
    }
}
