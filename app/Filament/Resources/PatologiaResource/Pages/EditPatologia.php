<?php

namespace App\Filament\Resources\PatologiaResource\Pages;

use App\Filament\Resources\PatologiaResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPatologia extends EditRecord
{
    protected static string $resource = PatologiaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
