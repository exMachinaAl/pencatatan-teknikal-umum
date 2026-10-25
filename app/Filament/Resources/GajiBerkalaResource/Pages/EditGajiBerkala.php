<?php

namespace App\Filament\Resources\GajiBerkalaResource\Pages;

use App\Filament\Resources\Concerns\RejectsStaleRecordUpdates;
use App\Filament\Resources\GajiBerkalaResource;
use App\Models\Pegawai;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditGajiBerkala extends EditRecord
{
    use RejectsStaleRecordUpdates;

    protected static string $resource = GajiBerkalaResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return $this->updateWithOptimisticLock($record, $data, function (Model $lockedRecord, array $data): array {
            $pegawai = Pegawai::whereKey($data['pegawai_id'])->lockForUpdate()->firstOrFail();

            if (! ($data['golongan_id'] ?? null) && ((int) $lockedRecord->pegawai_id !== (int) $pegawai->getKey() || ! $lockedRecord->golongan_id)) {
                $data['golongan_id'] = $pegawai->golongan_id;
            }

            return $data;
        });
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
