<?php

namespace App\Filament\Resources\GajiBerkalaResource\Pages;

use App\Filament\Resources\GajiBerkalaResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGajiBerkala extends EditRecord
{
    protected static string $resource = GajiBerkalaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
