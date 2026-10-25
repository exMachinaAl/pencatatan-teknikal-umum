<?php

namespace App\Models;

use App\Models\Concerns\HasOptimisticLock;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class KenaikanPangkat extends Model
{
    use HasFactory, HasOptimisticLock, SoftDeletes;

    protected $table = 'kenaikan_pangkat';

    protected $fillable = [
        'pegawai_id',
        'jenis',
        'golongan_lama_id',
        'golongan_baru_id',
        'periode',
        'tmt',
        'mk_tahun',
        'mk_bulan',
        'tautan_lampiran',
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
        'mk_tahun' => 'integer',
        'mk_bulan' => 'integer',
    ];

    public const STATUSES = [
        'berkas_disiapkan' => 'Berkas Disiapkan',
        'diajukan' => 'Diajukan',
        'sk_terbit' => 'SK Terbit',
        'diserahkan' => 'Diserahkan',
    ];

    private const STATUS_TRANSITIONS = [
        'berkas_disiapkan' => 'diajukan',
        'diajukan' => 'sk_terbit',
        'sk_terbit' => 'diserahkan',
        'diserahkan' => null,
    ];

    public const JENIS_KP = [
        'reguler' => 'Reguler (4 Tahunan)',
        'pilihan' => 'Pilihan (Jabatan Fungsional)',
    ];

    private const JENIS_KP_LEGACY = [
        'penyesuaian_ijazah' => 'Penyesuaian Ijazah',
    ];

    protected static function booted(): void
    {
        static::saving(function (KenaikanPangkat $model): void {
            $model->jenis ??= 'reguler';
            $model->status ??= 'berkas_disiapkan';
            $model->mk_tahun ??= 0;
            $model->mk_bulan ??= 0;

            if (! $model->pegawai_id) {
                throw ValidationException::withMessages(['pegawai_id' => 'Pegawai wajib dipilih.']);
            }

            $jenisValid = array_key_exists((string) $model->jenis, self::JENIS_KP)
                || ($model->exists
                    && ! $model->isDirty('jenis')
                    && array_key_exists((string) $model->jenis, self::JENIS_KP_LEGACY));

            if (! $jenisValid) {
                throw ValidationException::withMessages(['jenis' => 'Jenis kenaikan pangkat tidak valid.']);
            }

            if ($model->exists && $model->isDirty('status')) {
                $expected = self::STATUS_TRANSITIONS[(string) $model->getOriginal('status')] ?? null;
                if ($model->status !== $expected) {
                    throw ValidationException::withMessages([
                        'status' => 'Status kenaikan pangkat harus dilanjutkan satu tahap sesuai urutan proses.',
                    ]);
                }
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

            $attributes = $model->getAttributes();
            if (! preg_match('/^\d+$/D', (string) ($attributes['mk_tahun'] ?? ''))) {
                throw ValidationException::withMessages(['mk_tahun' => 'Masa kerja tahun harus berupa bilangan bulat.']);
            }
            if (! preg_match('/^\d+$/D', (string) ($attributes['mk_bulan'] ?? ''))) {
                throw ValidationException::withMessages(['mk_bulan' => 'Masa kerja bulan harus berupa bilangan bulat.']);
            }
            if ((int) $model->mk_tahun < 0 || (int) $model->mk_tahun > 60) {
                throw ValidationException::withMessages(['mk_tahun' => 'Masa kerja tahun harus antara 0 dan 60.']);
            }
            if ((int) $model->mk_bulan < 0 || (int) $model->mk_bulan > 11) {
                throw ValidationException::withMessages(['mk_bulan' => 'Masa kerja bulan harus antara 0 dan 11.']);
            }

            $date = $model->tmt ? Carbon::parse($model->tmt)->toDateString() : null;
            $integrityKey = static::integrityKey(
                (int) $model->pegawai_id,
                (string) $model->jenis,
                $date,
                (int) $model->mk_tahun,
                (int) $model->mk_bulan,
            );
            $duplicate = static::withTrashed()
                ->where('integrity_key', $integrityKey)
                ->when($model->exists, fn ($query) => $query->whereKeyNot($model->getKey()))
                ->exists();

            if ($duplicate) {
                $field = $date ? 'tmt' : 'mk_tahun';
                $message = $date
                    ? 'Pengajuan KP dengan pegawai, jenis, dan TMT yang sama sudah ada.'
                    : 'Pengajuan KP dengan pegawai, jenis, dan masa kerja yang sama sudah ada.';

                throw ValidationException::withMessages([$field => $message]);
            }

            $model->integrity_key = $integrityKey;
        });

        static::saved(function (KenaikanPangkat $model): void {
            $model->applyEffectiveRank();
        });
    }

    /**
     * Terapkan golongan resmi hanya setelah SK terbit dan TMT sudah berlaku.
     * Aman dipanggil berulang oleh scheduler karena pembaruan bersifat idempoten.
     */
    public function applyEffectiveRank(): bool
    {
        if (! in_array($this->status, ['sk_terbit', 'diserahkan'], true)
            || ! $this->golongan_baru_id
            || ! $this->tmt
            || Carbon::parse($this->tmt)->isFuture()) {
            return false;
        }

        return DB::transaction(function (): bool {
            $promotion = static::query()->whereKey($this->getKey())->lockForUpdate()->first();
            if (! $promotion
                || ! in_array($promotion->status, ['sk_terbit', 'diserahkan'], true)
                || ! $promotion->golongan_baru_id
                || ! $promotion->tmt
                || Carbon::parse($promotion->tmt)->isFuture()) {
                return false;
            }

            $date = Carbon::parse($promotion->tmt)->toDateString();
            $pegawai = Pegawai::query()->whereKey($promotion->pegawai_id)->lockForUpdate()->first();
            if (! $pegawai) {
                return false;
            }

            if ($pegawai->tmt_pangkat_terakhir && Carbon::parse($pegawai->tmt_pangkat_terakhir)->gt($date)) {
                return false;
            }

            if ((int) $pegawai->golongan_id === (int) $promotion->golongan_baru_id
                && $pegawai->tmt_pangkat_terakhir?->toDateString() === $date) {
                return false;
            }

            $pegawai->update([
                'golongan_id' => $promotion->golongan_baru_id,
                'tmt_pangkat_terakhir' => $date,
            ]);

            return true;
        }, 3);
    }

    public static function integrityKey(
        int $pegawaiId,
        string $jenis,
        ?string $tmt = null,
        int $mkTahun = 0,
        int $mkBulan = 0,
    ): string {
        $period = $tmt
            ? Carbon::parse($tmt)->toDateString()
            : "masa-kerja:{$mkTahun}:{$mkBulan}";

        return hash('sha256', implode('|', ['kp', $pegawaiId, $jenis, $period]));
    }

    public static function jenisLabel(?string $jenis): string
    {
        return self::JENIS_KP[$jenis]
            ?? self::JENIS_KP_LEGACY[$jenis]
            ?? (string) $jenis;
    }

    public function nextStatus(): ?string
    {
        return self::STATUS_TRANSITIONS[$this->status] ?? null;
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
