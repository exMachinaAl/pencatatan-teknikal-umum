<?php

namespace App\Observers;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class AuditObserver
{
    protected function cleanAttributes(array $attributes): array
    {
        // Jangan simpan data sensitif seperti password dalam log
        unset(
            $attributes['password'],
            $attributes['remember_token'],
            $attributes['tracking_token_hash'],
            $attributes['tracking_token_encrypted'],
            $attributes['lock_version'],
        );

        return $attributes;
    }

    public function created(Model $model): void
    {
        AuditLog::record(
            'create',
            class_basename($model),
            $model->getKey(),
            null,
            $this->cleanAttributes($model->getAttributes())
        );
    }

    public function updated(Model $model): void
    {
        // Hanya rekam jika ada perubahan atribut
        $changes = $this->cleanAttributes($model->getChanges());
        if (empty($changes)) {
            return;
        }

        $original = array_intersect_key($model->getOriginal(), $changes);

        AuditLog::record(
            'update',
            class_basename($model),
            $model->getKey(),
            $this->cleanAttributes($original),
            $changes
        );
    }

    public function deleted(Model $model): void
    {
        AuditLog::record(
            'delete',
            class_basename($model),
            $model->getKey(),
            $this->cleanAttributes($model->getAttributes()),
            null
        );
    }

    public function restored(Model $model): void
    {
        AuditLog::record(
            'restore',
            class_basename($model),
            $model->getKey(),
            null,
            $this->cleanAttributes($model->getAttributes())
        );
    }
}
