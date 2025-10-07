<?php

namespace App\Filament\Resources\ZonaAnatomicaResource\Pages;

use App\Filament\Resources\ZonaAnatomicaResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditZonaAnatomica extends EditRecord
{
    protected static string $resource = ZonaAnatomicaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
