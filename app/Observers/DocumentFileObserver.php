<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Models\Lampiran;
use App\Models\Legalisir;
use App\Models\SuratKeluar;
use App\Models\SuratMasuk;
use App\Support\SecureDocumentPath;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class DocumentFileObserver
{
    public function saving(Model $model): void
    {
        foreach ($this->fileFields($model) as $field => $label) {
            SecureDocumentPath::validate($model, $field, $model->getAttribute($field));
        }
    }

    public function created(Model $model): void
    {
        foreach ($this->fileFields($model) as $field => $label) {
            $this->syncCurrentFile($model, $field, $label);
        }
    }

    public function updated(Model $model): void
    {
        foreach ($this->fileFields($model) as $field => $label) {
            if ($model->wasChanged($field)) {
                $this->syncCurrentFile($model, $field, $label);
            }
        }
    }

    private function syncCurrentFile(Model $model, string $field, string $label): void
    {
        $entitas = class_basename($model);
        $current = Lampiran::query()
            ->where('entitas', $entitas)
            ->where('entitas_id', $model->getKey())
            ->where('keterangan', $label);

        $path = $model->getAttribute($field);
        if (! $path) {
            $current->update(['is_current' => false]);

            return;
        }

        if ($current->where('path', $path)->exists()) {
            return;
        }

        $versi = ((int) Lampiran::query()
            ->where('entitas', $entitas)
            ->where('entitas_id', $model->getKey())
            ->where('keterangan', $label)
            ->max('versi')) + 1;

        Lampiran::query()
            ->where('entitas', $entitas)
            ->where('entitas_id', $model->getKey())
            ->where('keterangan', $label)
            ->update(['is_current' => false]);

        $disk = Storage::disk('local');
        $exists = $disk->exists($path);
        $lampiran = Lampiran::create([
            'entitas' => $entitas,
            'entitas_id' => $model->getKey(),
            'nama_file' => basename($path),
            'path' => $path,
            'versi' => $versi,
            'is_current' => true,
            'akses' => 'internal',
            'keterangan' => $label,
            'mime' => $exists ? $disk->mimeType($path) : null,
            'ukuran_byte' => $exists ? $disk->size($path) : null,
            'checksum' => $exists ? hash_file('sha256', $disk->path($path)) : null,
            'uploaded_by' => Auth::id(),
        ]);

        AuditLog::record('upload_lampiran', $entitas, $model->getKey(), null, [
            'lampiran_id' => $lampiran->id,
            'nama_file' => $lampiran->nama_file,
            'versi' => $lampiran->versi,
        ]);
    }

    private function fileFields(Model $model): array
    {
        return match (true) {
            $model instanceof SuratMasuk => ['file_path' => 'Berkas utama'],
            $model instanceof SuratKeluar => [
                'file_final_path' => 'Berkas final',
                'bukti_pengiriman_path' => 'Bukti pengiriman',
            ],
            $model instanceof Legalisir => [
                'file_dokumen_path' => 'Dokumen verifikasi',
                'bukti_serah_terima_path' => 'Bukti serah terima',
            ],
            default => [],
        };
    }
}
