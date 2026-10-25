<?php

namespace App\Filament\Resources\SuratMasukResource\Pages;

use App\Filament\Resources\Concerns\RejectsStaleRecordUpdates;
use App\Filament\Resources\SuratMasukResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditSuratMasuk extends EditRecord
{
    use RejectsStaleRecordUpdates;

    protected static string $resource = SuratMasukResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return $this->updateWithOptimisticLock($record, $data);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
