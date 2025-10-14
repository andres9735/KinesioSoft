<?php

namespace App\Filament\Resources\TecnicaTipoResource\Pages;

use App\Filament\Resources\TecnicaTipoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTecnicaTipo extends EditRecord
{
    protected static string $resource = TecnicaTipoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
