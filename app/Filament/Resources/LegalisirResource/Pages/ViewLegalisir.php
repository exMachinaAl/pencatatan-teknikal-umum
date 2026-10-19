<?php

namespace App\Filament\Resources\LegalisirResource\Pages;

use App\Filament\Resources\LegalisirResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewLegalisir extends ViewRecord
{
    protected static string $resource = LegalisirResource::class;

    protected static string $view = 'filament.resources.pages.document-view';

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('tracking')
                ->label('Buka pelacakan')
                ->icon('heroicon-o-qr-code')
                ->url(fn () => $this->record->trackingUrl())
                ->openUrlInNewTab()
                ->visible(fn (): bool => filled($this->record->trackingUrl())),
            Actions\EditAction::make()->label('Edit legalisir'),
        ];
    }
}
