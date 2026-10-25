<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Models\Disposisi;
use App\Models\RelasiSurat;
use Illuminate\Database\Eloquent\Model;

class RelatedActivityObserver
{
    public function created(Model $model): void
    {
        $this->record($model, 'ditambahkan');
        $this->syncSuratMasukStatus($model);
    }

    public function updated(Model $model): void
    {
        $this->record($model, 'diperbarui', $model->getOriginal(), $model->getChanges());
        $this->syncSuratMasukStatus($model);
    }

    public function deleted(Model $model): void
    {
        $this->record($model, 'dihapus', $model->getOriginal());
        $this->syncSuratMasukStatus($model);
    }

    private function record(Model $model, string $action, ?array $before = null, ?array $after = null): void
    {
        if ($model instanceof Disposisi) {
            AuditLog::record("disposisi_{$action}", 'SuratMasuk', $model->surat_masuk_id, $before, $after ?? $model->toArray());
        }

        if ($model instanceof RelasiSurat) {
            $payload = $after ?? $model->toArray();
            AuditLog::record("relasi_surat_{$action}", 'SuratMasuk', $model->surat_masuk_id, $before, $payload);
            AuditLog::record("relasi_surat_{$action}", 'SuratKeluar', $model->surat_keluar_id, $before, $payload);
        }
    }

    private function syncSuratMasukStatus(Model $model): void
    {
        if (! $model instanceof Disposisi) {
            return;
        }

        $surat = $model->suratMasuk()->first();
        if (! $surat || $surat->status === 'diarsipkan') {
            return;
        }

        $statuses = $surat->disposisi()->pluck('status');
        $nextStatus = match (true) {
            $statuses->isEmpty() => 'baru',
            $statuses->every(fn (string $status): bool => $status === 'selesai') => 'selesai',
            default => 'didisposisi',
        };

        if ($surat->status !== $nextStatus) {
            $surat->update(['status' => $nextStatus]);
        }
    }
}
