<?php

namespace App\Models;

use App\Support\DocumentSequence;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class SuratMasuk extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'surat_masuk';

    protected $fillable = [
        'nomor_agenda',
        'nomor_surat',
        'tanggal_surat',
        'tanggal_diterima',
        'pengirim',
        'perihal',
        'sifat',
        'klasifikasi_id',
        'status',
        'lokasi_arsip_fisik',
        'file_path',
        'tautan_gform',
        'sumber',
        'diarsipkan_pada',
        'created_by',
    ];

    protected $casts = [
        'tanggal_surat' => 'date',
        'tanggal_diterima' => 'date',
        'diarsipkan_pada' => 'datetime',
    ];

    public const SIFAT = [
        'biasa' => 'Biasa',
        'penting' => 'Penting',
        'segera' => 'Segera',
        'rahasia' => 'Rahasia',
    ];

    public const STATUSES = [
        'baru' => 'Baru Diterima',
        'didisposisi' => 'Didisposisikan',
        'selesai' => 'Selesai Ditindaklanjuti',
        'diarsipkan' => 'Diarsipkan',
    ];

    protected static function booted(): void
    {
        static::saving(function (SuratMasuk $surat): void {
            $surat->perihal ??= '';
            $surat->sifat ??= 'biasa';
            $surat->status ??= 'baru';

            if (! $surat->tanggal_diterima) {
                throw ValidationException::withMessages(['tanggal_diterima' => 'Tanggal diterima wajib diisi.']);
            }

            if (! array_key_exists((string) $surat->sifat, self::SIFAT)) {
                throw ValidationException::withMessages(['sifat' => 'Sifat surat masuk tidak valid.']);
            }

            if (! array_key_exists((string) $surat->status, self::STATUSES)) {
                throw ValidationException::withMessages(['status' => 'Status surat masuk tidak valid.']);
            }

            if ($surat->tanggal_surat && $surat->tanggal_diterima && Carbon::parse($surat->tanggal_surat)->gt(Carbon::parse($surat->tanggal_diterima))) {
                throw ValidationException::withMessages(['tanggal_surat' => 'Tanggal surat tidak boleh setelah tanggal diterima.']);
            }
        });

        static::creating(function (SuratMasuk $surat): void {
            if (! $surat->nomor_agenda) {
                $tahun = Carbon::parse($surat->tanggal_diterima ?? now())->year;
                $surat->nomor_agenda = static::generateNomorAgenda($tahun);
            }
        });

        static::updating(function (SuratMasuk $surat): void {
            if ($surat->isDirty('nomor_agenda')) {
                throw ValidationException::withMessages(['nomor_agenda' => 'Nomor agenda tidak dapat diubah setelah tersimpan.']);
            }

            if (preg_match('/^SM\/(\d{4})\//', (string) $surat->nomor_agenda, $matches)
                && (int) $matches[1] !== Carbon::parse($surat->tanggal_diterima)->year) {
                throw ValidationException::withMessages(['tanggal_diterima' => 'Tahun tanggal diterima harus sesuai dengan tahun nomor agenda.']);
            }
        });
    }

    public static function generateNomorAgenda(?int $tahun = null): string
    {
        $tahun = $tahun ?? (int) date('Y');
        $pattern = '/^SM\/'.preg_quote((string) $tahun, '/').'\/(\d+)$/';
        $currentMaximum = static::withTrashed()
            ->where('nomor_agenda', 'like', "SM/{$tahun}/%")
            ->pluck('nomor_agenda')
            ->reduce(function (int $maximum, string $number) use ($pattern): int {
                return preg_match($pattern, $number, $matches)
                    ? max($maximum, (int) $matches[1])
                    : $maximum;
            }, 0);
        $next = DocumentSequence::next("surat_masuk:{$tahun}", $currentMaximum);

        return "SM/{$tahun}/".str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    public function klasifikasi(): BelongsTo
    {
        return $this->belongsTo(RefKlasifikasiSurat::class, 'klasifikasi_id');
    }

    public function disposisi(): HasMany
    {
        return $this->hasMany(Disposisi::class, 'surat_masuk_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lampiran(): MorphMany
    {
        return $this->morphMany(Lampiran::class, 'entitas', 'entitas', 'entitas_id');
    }

    public function suratBalasan(): BelongsToMany
    {
        return $this->belongsToMany(SuratKeluar::class, 'relasi_surat', 'surat_masuk_id', 'surat_keluar_id')
            ->withPivot(['jenis_relasi', 'catatan', 'created_by'])
            ->withTimestamps();
    }
}
