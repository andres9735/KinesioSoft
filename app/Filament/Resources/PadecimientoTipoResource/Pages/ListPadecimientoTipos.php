<?php

namespace App\Filament\Resources\PadecimientoTipoResource\Pages;

use App\Filament\Resources\PadecimientoTipoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPadecimientoTipos extends ListRecords
{
    protected static string $resource = PadecimientoTipoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
