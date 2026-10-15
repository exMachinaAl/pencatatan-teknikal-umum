<?php

namespace App\Filament\Resources\SuratMasukResource\Pages;

use App\Filament\Resources\SuratMasukResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewSuratMasuk extends ViewRecord
{
    protected static string $resource = SuratMasukResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()->label('Edit surat'),
        ];
    }
}
