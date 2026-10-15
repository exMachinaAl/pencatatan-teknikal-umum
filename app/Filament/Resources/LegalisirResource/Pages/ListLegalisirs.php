<?php

namespace App\Filament\Resources\LegalisirResource\Pages;

use App\Filament\Resources\LegalisirResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLegalisirs extends ListRecords
{
    protected static string $resource = LegalisirResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
