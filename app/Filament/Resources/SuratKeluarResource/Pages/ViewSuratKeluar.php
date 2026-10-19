<?php

namespace App\Filament\Resources\SuratKeluarResource\Pages;

use App\Filament\Resources\SuratKeluarResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewSuratKeluar extends ViewRecord
{
    protected static string $resource = SuratKeluarResource::class;

    protected static string $view = 'filament.resources.pages.document-view';

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()->label('Edit surat'),
        ];
    }
}
