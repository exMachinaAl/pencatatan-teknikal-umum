<?php

namespace App\Models\Concerns;

trait HasOptimisticLock
{
    public static function bootHasOptimisticLock(): void
    {
        static::updating(function ($model): void {
            $model->setAttribute('lock_version', (int) $model->getOriginal('lock_version') + 1);
        });
    }
}
