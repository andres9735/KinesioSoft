<?php

namespace App\Filament\Resources\BloqueDisponibilidadResource\Pages;

use App\Filament\Resources\BloqueDisponibilidadResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBloqueDisponibilidad extends EditRecord
{
    protected static string $resource = BloqueDisponibilidadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
