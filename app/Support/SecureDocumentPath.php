<?php

namespace App\Support;

use App\Models\Lampiran;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

final class SecureDocumentPath
{
    private const ALLOWED_DIRECTORIES = [
        'SuratMasuk' => ['file_path' => 'arsip_surat_masuk'],
        'SuratKeluar' => [
            'file_final_path' => 'arsip_surat_keluar',
            'bukti_pengiriman_path' => 'bukti_pengiriman',
        ],
        'Legalisir' => [
            'file_dokumen_path' => 'legalisir_dokumen',
            'bukti_serah_terima_path' => 'legalisir_serah_terima',
        ],
    ];

    public static function validate(Model $model, string $field, mixed $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        $path = str_replace('\\', '/', (string) $path);
        $entity = class_basename($model);
        $directory = self::ALLOWED_DIRECTORIES[$entity][$field] ?? null;
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if ($directory === null
            || str_starts_with($path, '/')
            || str_contains($path, '../')
            || ! str_starts_with($path, $directory.'/')
            || ! in_array($extension, ['pdf', 'jpg', 'jpeg', 'png'], true)) {
            throw ValidationException::withMessages([
                $field => 'Referensi lampiran tidak sah. Unggah kembali berkas melalui kolom yang tersedia.',
            ]);
        }

        $usedByAnotherRecord = Lampiran::query()
            ->where('path', $path)
            ->when($model->exists, fn ($query) => $query->where(function ($owner) use ($entity, $model): void {
                $owner->where('entitas', '!=', $entity)
                    ->orWhere('entitas_id', '!=', $model->getKey());
            }))
            ->when(! $model->exists, fn ($query) => $query)
            ->exists();

        if ($usedByAnotherRecord) {
            throw ValidationException::withMessages([
                $field => 'Lampiran sudah terikat pada data lain dan tidak dapat digunakan ulang.',
            ]);
        }
    }

    public static function fieldForAttachment(Lampiran $attachment): ?string
    {
        return match (class_basename((string) $attachment->entitas).':'.$attachment->keterangan) {
            'SuratMasuk:Berkas utama' => 'file_path',
            'SuratKeluar:Berkas final' => 'file_final_path',
            'SuratKeluar:Bukti pengiriman' => 'bukti_pengiriman_path',
            'Legalisir:Dokumen verifikasi' => 'file_dokumen_path',
            'Legalisir:Bukti serah terima' => 'bukti_serah_terima_path',
            default => null,
        };
    }
}
