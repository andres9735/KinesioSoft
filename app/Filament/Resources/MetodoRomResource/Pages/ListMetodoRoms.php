<?php

namespace App\Filament\Resources\MetodoRomResource\Pages;

use App\Filament\Resources\MetodoRomResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMetodoRoms extends ListRecords
{
    protected static string $resource = MetodoRomResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
