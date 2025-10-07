<?php

namespace App\Filament\Resources\ZonaAnatomicaResource\Pages;

use App\Filament\Resources\ZonaAnatomicaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListZonaAnatomicas extends ListRecords
{
    protected static string $resource = ZonaAnatomicaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
