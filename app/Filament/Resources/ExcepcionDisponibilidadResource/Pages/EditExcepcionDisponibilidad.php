<?php

namespace App\Filament\Resources\ExcepcionDisponibilidadResource\Pages;

use App\Filament\Resources\ExcepcionDisponibilidadResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditExcepcionDisponibilidad extends EditRecord
{
    protected static string $resource = ExcepcionDisponibilidadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
