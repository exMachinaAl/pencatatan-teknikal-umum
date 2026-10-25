<?php

namespace App\Filament\Resources\PegawaiResource\Pages;

use App\Filament\Resources\Concerns\RejectsStaleRecordUpdates;
use App\Filament\Resources\PegawaiResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditPegawai extends EditRecord
{
    use RejectsStaleRecordUpdates;

    protected static string $resource = PegawaiResource::class;

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
