<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ConcurrentRecordUpdate
{
    public static function run(Model $record, callable $callback): Model
    {
        return DB::transaction(function () use ($record, $callback): Model {
            $locked = $record->newQuery()->whereKey($record->getKey())->lockForUpdate()->firstOrFail();

            if ((int) $locked->getAttribute('lock_version') !== (int) $record->getAttribute('lock_version')) {
                throw ValidationException::withMessages([
                    'record' => 'Data sudah berubah karena tindakan pengguna lain. Muat ulang tabel lalu coba kembali.',
                ]);
            }

            $callback($locked);

            return $locked;
        }, 3);
    }
}
