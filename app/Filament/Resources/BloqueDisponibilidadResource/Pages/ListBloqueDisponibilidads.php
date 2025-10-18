<?php

namespace App\Filament\Resources\BloqueDisponibilidadResource\Pages;

use App\Filament\Resources\BloqueDisponibilidadResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBloqueDisponibilidads extends ListRecords
{
    protected static string $resource = BloqueDisponibilidadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
