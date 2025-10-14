<?php

namespace App\Filament\Resources\TecnicaTipoResource\Pages;

use App\Filament\Resources\TecnicaTipoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTecnicaTipos extends ListRecords
{
    protected static string $resource = TecnicaTipoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
