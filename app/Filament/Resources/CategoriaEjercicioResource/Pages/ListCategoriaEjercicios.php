<?php

namespace App\Filament\Resources\CategoriaEjercicioResource\Pages;

use App\Filament\Resources\CategoriaEjercicioResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCategoriaEjercicios extends ListRecords
{
    protected static string $resource = CategoriaEjercicioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
