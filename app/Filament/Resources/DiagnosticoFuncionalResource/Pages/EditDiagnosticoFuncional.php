<?php

namespace App\Filament\Resources\DiagnosticoFuncionalResource\Pages;

use App\Filament\Resources\DiagnosticoFuncionalResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDiagnosticoFuncional extends EditRecord
{
    protected static string $resource = DiagnosticoFuncionalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
