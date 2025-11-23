<?php

namespace App\Filament\Kinesiologa\Resources\PacienteResource\Pages;

use App\Filament\Kinesiologa\Resources\PacienteResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPaciente extends ViewRecord
{
    protected static string $resource = PacienteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return static::getResource()::getWidgets();
    }

}
