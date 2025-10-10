<?php

namespace App\Filament\Resources\CategoriaEjercicioResource\Pages;

use App\Filament\Resources\CategoriaEjercicioResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCategoriaEjercicio extends EditRecord
{
    protected static string $resource = CategoriaEjercicioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
