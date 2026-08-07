<?php

namespace Tests\Unit\Services;

use App\Models\Paciente;
use App\Services\GeneradorNumeroHistoriaClinica;
use LogicException;
use PHPUnit\Framework\TestCase;

class GeneradorNumeroHistoriaClinicaTest extends TestCase
{
    public function test_generates_expected_number_from_paciente_id(): void
    {
        $paciente = $this->persistedPacienteWithId(27);

        $numero = (new GeneradorNumeroHistoriaClinica())->generar($paciente);

        $this->assertSame('HC-000027', $numero);
    }

    public function test_keeps_six_digit_id_without_extra_padding(): void
    {
        $paciente = $this->persistedPacienteWithId(123456);

        $numero = (new GeneradorNumeroHistoriaClinica())->generar($paciente);

        $this->assertSame('HC-123456', $numero);
    }

    public function test_throws_when_paciente_is_not_persisted(): void
    {
        $paciente = new Paciente();
        $paciente->setAttribute('id', 27);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(
            'El paciente debe estar persistido antes de generar su número de historia clínica.'
        );

        (new GeneradorNumeroHistoriaClinica())->generar($paciente);
    }

    public function test_throws_when_persisted_paciente_has_no_id(): void
    {
        $paciente = new Paciente();
        $paciente->exists = true;

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(
            'El paciente debe estar persistido antes de generar su número de historia clínica.'
        );

        (new GeneradorNumeroHistoriaClinica())->generar($paciente);
    }

    private function persistedPacienteWithId(int $id): Paciente
    {
        $paciente = new Paciente();
        $paciente->setAttribute('id', $id);
        $paciente->exists = true;

        return $paciente;
    }
}
