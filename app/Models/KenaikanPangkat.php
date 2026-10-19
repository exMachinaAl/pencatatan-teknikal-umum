<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class KenaikanPangkat extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'kenaikan_pangkat';

    protected $fillable = [
        'pegawai_id',
        'jenis',
        'golongan_lama_id',
        'golongan_baru_id',
        'periode',
        'tmt',
        'nomor_sk',
        'tanggal_sk',
        'pejabat_penetap',
        'status',
        'catatan',
        'created_by',
    ];

    protected $casts = [
        'tmt' => 'date',
        'tanggal_sk' => 'date',
    ];

    public const STATUSES = [
        'berkas_disiapkan' => 'Berkas Disiapkan',
        'diajukan' => 'Diajukan',
        'sk_terbit' => 'SK Terbit',
        'diserahkan' => 'Diserahkan',
    ];

    public const JENIS_KP = [
        'reguler' => 'Reguler (4 Tahunan)',
        'pilihan' => 'Pilihan (Jabatan Fungsional)',
        'penyesuaian_ijazah' => 'Penyesuaian Ijazah',
    ];

    protected static function booted(): void
    {
        static::saving(function (KenaikanPangkat $model): void {
            $model->jenis ??= 'reguler';
            $model->status ??= 'berkas_disiapkan';

            if (! $model->pegawai_id) {
                throw ValidationException::withMessages(['pegawai_id' => 'Pegawai wajib dipilih.']);
            }

            if (! $model->tmt) {
                throw ValidationException::withMessages(['tmt' => 'TMT kenaikan pangkat wajib diisi.']);
            }

            if (! array_key_exists((string) $model->jenis, self::JENIS_KP)) {
                throw ValidationException::withMessages(['jenis' => 'Jenis kenaikan pangkat tidak valid.']);
            }

            if (! array_key_exists((string) $model->status, self::STATUSES)) {
                throw ValidationException::withMessages(['status' => 'Status kenaikan pangkat tidak valid.']);
            }

            if ($model->periode !== null && ! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D', (string) $model->periode)) {
                throw ValidationException::withMessages(['periode' => 'Periode harus menggunakan format YYYY-MM.']);
            }

            foreach (['nomor_sk' => 100, 'pejabat_penetap' => 150, 'catatan' => 20000] as $field => $maximum) {
                if ($model->{$field} !== null && mb_strlen((string) $model->{$field}, 'UTF-8') > $maximum) {
                    throw ValidationException::withMessages([$field => "Panjang {$field} melebihi batas {$maximum} karakter."]);
                }
            }

            if ($model->golongan_lama_id && $model->golongan_baru_id && (int) $model->golongan_lama_id === (int) $model->golongan_baru_id) {
                throw ValidationException::withMessages(['golongan_baru_id' => 'Golongan baru harus berbeda dari golongan lama.']);
            }

            $date = Carbon::parse($model->tmt)->toDateString();
            $duplicate = static::withTrashed()
                ->where('pegawai_id', $model->pegawai_id)
                ->where('jenis', $model->jenis)
                ->whereDate('tmt', $date)
                ->when($model->exists, fn ($query) => $query->whereKeyNot($model->getKey()))
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages(['tmt' => 'Pengajuan KP dengan pegawai, jenis, dan TMT yang sama sudah ada.']);
            }

            $model->integrity_key = static::integrityKey((int) $model->pegawai_id, (string) $model->jenis, $date);
        });

        static::saved(function (KenaikanPangkat $model): void {
            if (! in_array($model->status, ['sk_terbit', 'diserahkan'], true) || ! $model->golongan_baru_id || ! $model->tmt) {
                return;
            }

            $date = Carbon::parse($model->tmt)->toDateString();
            Pegawai::query()
                ->whereKey($model->pegawai_id)
                ->where(function ($query) use ($date): void {
                    $query->whereNull('tmt_pangkat_terakhir')
                        ->orWhereDate('tmt_pangkat_terakhir', '<=', $date);
                })
                ->update([
                    'golongan_id' => $model->golongan_baru_id,
                    'tmt_pangkat_terakhir' => $date,
                ]);
        });
    }

    public static function integrityKey(int $pegawaiId, string $jenis, string $tmt): string
    {
        return hash('sha256', implode('|', ['kp', $pegawaiId, $jenis, Carbon::parse($tmt)->toDateString()]));
    }

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id');
    }

    public function golonganLama(): BelongsTo
    {
        return $this->belongsTo(RefGolongan::class, 'golongan_lama_id');
    }

    public function golonganBaru(): BelongsTo
    {
        return $this->belongsTo(RefGolongan::class, 'golongan_baru_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lampiran(): MorphMany
    {
        return $this->morphMany(Lampiran::class, 'entitas', 'entitas', 'entitas_id');
    }
}
