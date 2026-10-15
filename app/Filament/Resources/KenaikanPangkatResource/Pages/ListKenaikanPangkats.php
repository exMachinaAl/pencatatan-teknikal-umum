<?php

namespace App\Filament\Resources\KenaikanPangkatResource\Pages;

use App\Filament\Resources\KenaikanPangkatResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListKenaikanPangkats extends ListRecords
{
    protected static string $resource = KenaikanPangkatResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
