<?php

namespace App\Filament\Resources\LegalisirResource\Pages;

use App\Filament\Resources\LegalisirResource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Filament\Resources\Pages\CreateRecord;

class CreateLegalisir extends CreateRecord
{
    protected static string $resource = LegalisirResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data): Model {
            $data['petugas_id'] = Auth::id();

            return static::getModel()::create($data);
        });
    }
}
