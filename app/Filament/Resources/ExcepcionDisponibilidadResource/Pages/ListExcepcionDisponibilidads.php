<?php

namespace App\Filament\Resources\ExcepcionDisponibilidadResource\Pages;

use App\Filament\Resources\ExcepcionDisponibilidadResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListExcepcionDisponibilidads extends ListRecords
{
    protected static string $resource = ExcepcionDisponibilidadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
