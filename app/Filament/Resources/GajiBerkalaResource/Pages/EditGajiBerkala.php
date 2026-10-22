<?php

namespace App\Filament\Resources\GajiBerkalaResource\Pages;

use App\Filament\Resources\GajiBerkalaResource;
use App\Models\Pegawai;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class EditGajiBerkala extends EditRecord
{
    protected static string $resource = GajiBerkalaResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return DB::transaction(function () use ($record, $data): Model {
            $pegawai = Pegawai::whereKey($data['pegawai_id'])->lockForUpdate()->firstOrFail();
            $lockedRecord = $record->newQuery()->whereKey($record->getKey())->lockForUpdate()->firstOrFail();

            if (! ($data['golongan_id'] ?? null) && ((int) $lockedRecord->pegawai_id !== (int) $pegawai->getKey() || ! $lockedRecord->golongan_id)) {
                $data['golongan_id'] = $pegawai->golongan_id;
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
