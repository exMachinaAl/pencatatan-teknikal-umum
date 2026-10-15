<?php

namespace App\Filament\Resources\KenaikanPangkatResource\Pages;

use App\Filament\Resources\KenaikanPangkatResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditKenaikanPangkat extends EditRecord
{
    protected static string $resource = KenaikanPangkatResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
