<?php

namespace App\Filament\Resources\EquipoTerapeuticoResource\Pages;

use App\Filament\Resources\EquipoTerapeuticoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEquipoTerapeuticos extends ListRecords
{
    protected static string $resource = EquipoTerapeuticoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
