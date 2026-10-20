<?php

namespace App\Filament\Resources\KenaikanPangkatResource\Pages;

use App\Filament\Resources\KenaikanPangkatResource;
use App\Models\Pegawai;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class EditKenaikanPangkat extends EditRecord
{
    protected static string $resource = KenaikanPangkatResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return DB::transaction(function () use ($record, $data): Model {
            $pegawai = Pegawai::whereKey($data['pegawai_id'])->lockForUpdate()->firstOrFail();
            $lockedRecord = $record->newQuery()->whereKey($record->getKey())->lockForUpdate()->firstOrFail();

            if (! ($data['golongan_lama_id'] ?? null) && ((int) $lockedRecord->pegawai_id !== (int) $pegawai->getKey() || ! $lockedRecord->golongan_lama_id)) {
                $data['golongan_lama_id'] = $pegawai->golongan_id;
            }

            $lockedRecord->update($data);

            return $lockedRecord;
        });
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
