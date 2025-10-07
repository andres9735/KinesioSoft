<?php

namespace App\Filament\Resources\PatologiaResource\Pages;

use App\Filament\Resources\PatologiaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPatologias extends ListRecords
{
    protected static string $resource = PatologiaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
