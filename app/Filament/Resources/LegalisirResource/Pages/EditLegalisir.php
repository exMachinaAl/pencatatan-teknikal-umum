<?php

namespace App\Filament\Resources\LegalisirResource\Pages;

use App\Filament\Resources\LegalisirResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLegalisir extends EditRecord
{
    protected static string $resource = LegalisirResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
