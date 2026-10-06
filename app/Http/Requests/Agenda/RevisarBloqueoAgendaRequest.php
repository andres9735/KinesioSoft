<?php

namespace App\Http\Requests\Agenda;

use App\Models\BloqueoAgenda;

class RevisarBloqueoAgendaRequest extends BloqueoAgendaRequestBase
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', BloqueoAgenda::class) ?? false;
    }

    public function rules(): array
    {
        return $this->reglasCandidato(incluyeSnapshot: false);
    }
}
