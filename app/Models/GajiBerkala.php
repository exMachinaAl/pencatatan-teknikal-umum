<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

class GajiBerkala extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'gaji_berkala';

    protected $fillable = [
        'pegawai_id',
        'golongan_id',
        'tmt',
        'gaji_pokok_lama',
        'gaji_pokok_baru',
        'mk_tahun',
        'mk_bulan',
        'tautan_lampiran',
        'nomor_sk',
        'tanggal_sk',
        'pejabat_penetap',
        'tmt_berikutnya',
        'status',
        'catatan',
        'created_by',
    ];

    protected $casts = [
        'tmt' => 'date',
        'tanggal_sk' => 'date',
        'tmt_berikutnya' => 'date',
        'gaji_pokok_lama' => 'decimal:2',
        'gaji_pokok_baru' => 'decimal:2',
        'mk_tahun' => 'integer',
        'mk_bulan' => 'integer',
    ];

    public const STATUSES = [
        'diproses' => 'Diproses',
        'sk_terbit' => 'SK Terbit',
        'diserahkan' => 'Diserahkan',
    ];

    protected static function booted(): void
    {
        static::saving(function (GajiBerkala $model): void {
            $model->status ??= 'diproses';
            $model->mk_tahun ??= 0;
            $model->mk_bulan ??= 0;

            if (! $model->pegawai_id) {
                throw ValidationException::withMessages(['pegawai_id' => 'Pegawai wajib dipilih.']);
            }

            if (! $model->tmt_berikutnya && $model->tmt) {
                $model->tmt_berikutnya = Carbon::parse($model->tmt)->addYears(2);
            }

            if (! array_key_exists((string) $model->status, self::STATUSES)) {
                throw ValidationException::withMessages(['status' => 'Status KGB tidak valid.']);
            }

            foreach (['nomor_sk' => 100, 'pejabat_penetap' => 150, 'catatan' => 20000] as $field => $maximum) {
                if ($model->{$field} !== null && mb_strlen((string) $model->{$field}, 'UTF-8') > $maximum) {
                    throw ValidationException::withMessages([$field => "Panjang {$field} melebihi batas {$maximum} karakter."]);
                }
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

            foreach (['gaji_pokok_lama', 'gaji_pokok_baru'] as $field) {
                $rawValue = $attributes[$field] ?? null;
                if ($rawValue !== null && (! is_numeric($rawValue) || (float) $rawValue < 0 || (float) $rawValue > 999_999_999_999.99)) {
                    throw ValidationException::withMessages([$field => 'Nominal gaji harus berupa angka 0 sampai 999.999.999.999,99.']);
                }
            }

            if ($model->gaji_pokok_lama !== null && $model->gaji_pokok_baru !== null && (float) $model->gaji_pokok_baru < (float) $model->gaji_pokok_lama) {
                throw ValidationException::withMessages(['gaji_pokok_baru' => 'Gaji pokok baru tidak boleh lebih kecil dari gaji lama.']);
            }

            $date = $model->tmt ? Carbon::parse($model->tmt)->toDateString() : null;
            if ($date && $model->tmt_berikutnya && Carbon::parse($model->tmt_berikutnya)->lte(Carbon::parse($date))) {
                throw ValidationException::withMessages(['tmt_berikutnya' => 'TMT berikutnya harus setelah TMT KGB.']);
            }

            $integrityKey = static::integrityKey(
                (int) $model->pegawai_id,
                $date,
                $model->golongan_id ? (int) $model->golongan_id : null,
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
                    ? 'Riwayat KGB dengan pegawai dan TMT yang sama sudah ada.'
                    : 'Data KGB dengan pegawai, golongan, dan masa kerja yang sama sudah ada.';

                throw ValidationException::withMessages([$field => $message]);
            }

            $model->integrity_key = $integrityKey;
        });

        static::saved(function (GajiBerkala $model): void {
            if ($model->status !== 'diserahkan' || ! $model->tmt) {
                return;
            }

            $date = Carbon::parse($model->tmt)->toDateString();
            Pegawai::query()
                ->whereKey($model->pegawai_id)
                ->where(function ($query) use ($date): void {
                    $query->whereNull('tmt_kgb_terakhir')
                        ->orWhereDate('tmt_kgb_terakhir', '<=', $date);
                })
                ->update(['tmt_kgb_terakhir' => $date]);
        });
    }

    public static function integrityKey(
        int $pegawaiId,
        ?string $tmt = null,
        ?int $golonganId = null,
        int $mkTahun = 0,
        int $mkBulan = 0,
    ): string {
        $period = $tmt
            ? Carbon::parse($tmt)->toDateString()
            : "golongan:{$golonganId}:masa-kerja:{$mkTahun}:{$mkBulan}";

        return hash('sha256', implode('|', ['kgb', $pegawaiId, $period]));
    }

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id');
    }

    public function golongan(): BelongsTo
    {
        return $this->belongsTo(RefGolongan::class, 'golongan_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lampiran(): MorphMany
    {
        return $this->morphMany(Lampiran::class, 'entitas', 'entitas', 'entitas_id');
    }

    // Scope jatuh tempo dalam rentang bulan tertentu dari sekarang
    public function scopeJatuhTempo(Builder $query, int $bulan = 3): Builder
    {
        return $query->whereNotNull('tmt_berikutnya')
            ->whereDate('tmt_berikutnya', '<=', now()->addMonths($bulan)->toDateString())
            ->whereDate('tmt_berikutnya', '>=', now()->subMonth()->toDateString());
    }

    public function scopePerluTindakLanjut(Builder $query): Builder
    {
        return $query->jatuhTempo()->where('status', '!=', 'diserahkan');
    }
}
