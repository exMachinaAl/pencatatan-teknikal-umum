<?php

namespace App\Filament\Resources\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

trait RejectsStaleRecordUpdates
{
    public int $loadedLockVersion = 0;

    public function mount(int|string $record): void
    {
        parent::mount($record);
        $this->loadedLockVersion = (int) $this->getRecord()->getAttribute('lock_version');
    }

    /**
     * @param  null|callable(Model, array<string, mixed>): array<string, mixed>  $prepare
     */
    protected function updateWithOptimisticLock(Model $record, array $data, ?callable $prepare = null): Model
    {
        return DB::transaction(function () use ($record, $data, $prepare): Model {
            $locked = $record->newQuery()->whereKey($record->getKey())->lockForUpdate()->firstOrFail();

            if ((int) $locked->getAttribute('lock_version') !== $this->loadedLockVersion) {
                throw ValidationException::withMessages([
                    'data' => 'Data ini sudah diubah oleh pengguna lain. Muat ulang halaman, periksa perubahan terbaru, lalu simpan kembali.',
                ]);
            }

            if ($prepare !== null) {
                $data = $prepare($locked, $data);
            }

            $locked->fill($data);
            $locked->save();
            $this->loadedLockVersion = (int) $locked->getAttribute('lock_version');

            return $locked;
        }, 3);
    }
}
