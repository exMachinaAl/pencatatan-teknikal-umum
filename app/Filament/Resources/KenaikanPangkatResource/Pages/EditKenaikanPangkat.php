<?php

namespace App\Filament\Resources\KenaikanPangkatResource\Pages;

use App\Filament\Resources\Concerns\RejectsStaleRecordUpdates;
use App\Filament\Resources\KenaikanPangkatResource;
use App\Models\Pegawai;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditKenaikanPangkat extends EditRecord
{
    use RejectsStaleRecordUpdates;

    protected static string $resource = KenaikanPangkatResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return $this->updateWithOptimisticLock($record, $data, function (Model $lockedRecord, array $data): array {
            $pegawai = Pegawai::whereKey($data['pegawai_id'])->lockForUpdate()->firstOrFail();

            if (! ($data['golongan_lama_id'] ?? null) && ((int) $lockedRecord->pegawai_id !== (int) $pegawai->getKey() || ! $lockedRecord->golongan_lama_id)) {
                $data['golongan_lama_id'] = $pegawai->golongan_id;
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
