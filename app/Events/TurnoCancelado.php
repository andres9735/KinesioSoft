<?php

namespace App\Events;

use App\Models\Turno;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TurnoCancelado
{
    use Dispatchable, SerializesModels;

    public Turno $turno;
    public bool $esTemprano;

    public function __construct(Turno $turno, bool $esTemprano)
    {
        $this->turno = $turno;
        $this->esTemprano = $esTemprano;
    }
}
