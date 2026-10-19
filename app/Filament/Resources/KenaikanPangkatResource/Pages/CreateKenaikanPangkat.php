<?php

namespace App\Filament\Resources\KenaikanPangkatResource\Pages;

use App\Filament\Resources\KenaikanPangkatResource;
use App\Models\Pegawai;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CreateKenaikanPangkat extends CreateRecord
{
    protected static string $resource = KenaikanPangkatResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data): Model {
            Pegawai::whereKey($data['pegawai_id'])->lockForUpdate()->firstOrFail();
            $data['created_by'] = Auth::id();

            return static::getModel()::create($data);
        });
    }
}
