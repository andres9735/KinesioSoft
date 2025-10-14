<?php

namespace App\Filament\Resources\TecnicaResource\Pages;

use App\Filament\Resources\TecnicaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTecnicas extends ListRecords
{
    protected static string $resource = TecnicaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
