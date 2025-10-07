<?php

namespace App\Filament\Resources\PadecimientoTipoResource\Pages;

use App\Filament\Resources\PadecimientoTipoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPadecimientoTipo extends EditRecord
{
    protected static string $resource = PadecimientoTipoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
