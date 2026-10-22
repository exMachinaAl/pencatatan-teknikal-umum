<?php

namespace App\Filament\Resources\GajiBerkalaResource\Pages;

use App\Filament\Resources\GajiBerkalaResource;
use App\Models\Pegawai;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CreateGajiBerkala extends CreateRecord
{
    protected static string $resource = GajiBerkalaResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data): Model {
            $pegawai = Pegawai::whereKey($data['pegawai_id'])->lockForUpdate()->firstOrFail();
            $data['golongan_id'] ??= $pegawai->golongan_id;
            $data['created_by'] = Auth::id();

            return static::getModel()::create($data);
        });
    }
}
