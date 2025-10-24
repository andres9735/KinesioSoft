<?php

namespace App\Filament\Resources\MetodoRomResource\Pages;

use App\Filament\Resources\MetodoRomResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMetodoRom extends EditRecord
{
    protected static string $resource = MetodoRomResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
