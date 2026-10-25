<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\Concerns\RejectsStaleRecordUpdates;
use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditUser extends EditRecord
{
    use RejectsStaleRecordUpdates;

    protected static string $resource = UserResource::class;

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
