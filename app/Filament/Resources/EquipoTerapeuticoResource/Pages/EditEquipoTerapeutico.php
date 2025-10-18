<?php

namespace App\Filament\Resources\EquipoTerapeuticoResource\Pages;

use App\Filament\Resources\EquipoTerapeuticoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEquipoTerapeutico extends EditRecord
{
    protected static string $resource = EquipoTerapeuticoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
