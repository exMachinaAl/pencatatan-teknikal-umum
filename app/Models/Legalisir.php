<?php

namespace App\Models;

use App\Models\Concerns\HasOptimisticLock;
use App\Support\DocumentSequence;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Legalisir extends Model
{
    use HasFactory, HasOptimisticLock, SoftDeletes;

    protected $table = 'legalisir';

    protected $fillable = [
        'nomor_legalisir',
        'nomor_antrean',
        'tracking_token_hash',
        'tracking_token_encrypted',
        'nama_pemohon',
        'kontak',
        'email_pemohon',
        'kanal_notifikasi',
        'status_pemohon',
        'jenis_dokumen',
        'nomor_dokumen',
        'lokasi_arsip_fisik',
        'tahun_lulus',
        'jumlah_lembar',
        'dokumen_asli_dilihat',
        'identitas_cocok',
        'data_sekolah_cocok',
        'tanggal_permohonan',
        'tanggal_selesai',
        'jadwal_pengambilan',
        'tanggal_diambil',
        'biaya',
        'pengambil',
        'hubungan_pengambil',
        'bukti_serah_terima_path',
        'status',
        'petugas_id',
        'diverifikasi_oleh',
        'diverifikasi_pada',
        'catatan',
        'alasan_penolakan',
        'file_dokumen_path',
        'tautan_lampiran',
    ];

    protected $casts = [
        'tanggal_permohonan' => 'date',
        'tanggal_selesai' => 'date',
        'tanggal_diambil' => 'date',
        'jadwal_pengambilan' => 'datetime',
        'diverifikasi_pada' => 'datetime',
        'biaya' => 'decimal:2',
        'jumlah_lembar' => 'integer',
        'tahun_lulus' => 'integer',
        'dokumen_asli_dilihat' => 'boolean',
        'identitas_cocok' => 'boolean',
        'data_sekolah_cocok' => 'boolean',
        'tracking_token_encrypted' => 'encrypted',
    ];

    public const STATUS_PEMOHON = [
        'alumni' => 'Alumni',
        'siswa' => 'Siswa Aktif',
        'wali' => 'Orang Tua / Wali',
        'lainnya' => 'Lainnya / Instansi',
    ];

    public const STATUSES = [
        'diterima' => 'Permohonan Diterima',
        'diproses' => 'Sedang Diverifikasi / Diproses',
        'siap_diambil' => 'Selesai & Siap Diambil',
        'diambil' => 'Sudah Diambil',
        'ditolak' => 'Ditolak / Berkas Tidak Sesuai',
    ];

    private const STATUS_TRANSITIONS = [
        'diterima' => ['diproses', 'ditolak'],
        'diproses' => ['siap_diambil', 'ditolak'],
        'siap_diambil' => ['diambil'],
        'diambil' => [],
        'ditolak' => [],
    ];

    public const JENIS_DOKUMEN = [
        'Ijazah' => 'Ijazah SMA',
        'SKHUN' => 'SKHUN',
        'Transkrip Nilai' => 'Transkrip Nilai / Rapor Semester',
        'Surat Keterangan Pengganti Ijazah' => 'Surat Keterangan Pengganti Ijazah',
        'Sertifikat Akreditasi' => 'Sertifikat Akreditasi Sekolah',
        'Lainnya' => 'Dokumen Lainnya',
    ];

    public const KANAL_NOTIFIKASI = [
        'tidak_ada' => 'Tanpa notifikasi',
        'email' => 'Email',
        'whatsapp' => 'WhatsApp',
        'keduanya' => 'Email dan WhatsApp',
    ];

    protected static function booted(): void
    {
        static::saving(function (Legalisir $legalisir): void {
            $legalisir->status ??= 'diterima';
            $legalisir->status_pemohon ??= 'alumni';
            $legalisir->kanal_notifikasi ??= 'tidak_ada';
            $legalisir->jumlah_lembar ??= 1;
            $legalisir->lokasi_arsip_fisik = trim((string) $legalisir->lokasi_arsip_fisik) ?: null;
            if (! array_key_exists('biaya', $legalisir->getAttributes()) || $legalisir->getAttributes()['biaya'] === null) {
                $legalisir->setAttribute('biaya', 0);
            }

            if (! $legalisir->tanggal_permohonan) {
                throw ValidationException::withMessages(['tanggal_permohonan' => 'Tanggal permohonan wajib diisi.']);
            }

            if (! array_key_exists((string) $legalisir->status, self::STATUSES)) {
                throw ValidationException::withMessages(['status' => 'Status legalisir tidak valid.']);
            }
            if ($legalisir->exists && $legalisir->isDirty('status')) {
                $allowed = self::STATUS_TRANSITIONS[(string) $legalisir->getOriginal('status')] ?? [];
                if (! in_array($legalisir->status, $allowed, true)) {
                    throw ValidationException::withMessages([
                        'status' => 'Perubahan status legalisir tidak sesuai urutan proses.',
                    ]);
                }
            }
            if (! array_key_exists((string) $legalisir->status_pemohon, self::STATUS_PEMOHON)) {
                throw ValidationException::withMessages(['status_pemohon' => 'Status pemohon tidak valid.']);
            }
            if (! array_key_exists((string) $legalisir->kanal_notifikasi, self::KANAL_NOTIFIKASI)) {
                throw ValidationException::withMessages(['kanal_notifikasi' => 'Kanal notifikasi tidak valid.']);
            }
            if (in_array($legalisir->status, ['siap_diambil', 'diambil'], true)) {
                if (! $legalisir->checklistLengkap()) {
                    throw ValidationException::withMessages(['status' => 'Dokumen hanya dapat dinyatakan siap setelah seluruh checklist verifikasi terpenuhi.']);
                }

                $legalisir->diverifikasi_oleh ??= Auth::id();
                $legalisir->diverifikasi_pada ??= now();
                $legalisir->tanggal_selesai ??= now()->toDateString();

                if (! $legalisir->diverifikasi_oleh) {
                    throw ValidationException::withMessages(['status' => 'Identitas petugas verifikasi tidak tersedia. Silakan masuk kembali.']);
                }
            }

            if ($legalisir->status === 'diambil' && (! $legalisir->tanggal_diambil || blank($legalisir->pengambil))) {
                throw ValidationException::withMessages(['status' => 'Tanggal dan nama pengambil wajib diisi sebelum dokumen ditandai sudah diambil.']);
            }
            if ($legalisir->status === 'ditolak' && blank($legalisir->alasan_penolakan)) {
                throw ValidationException::withMessages(['alasan_penolakan' => 'Alasan penolakan wajib diisi.']);
            }
            if ($legalisir->lokasi_arsip_fisik !== null
                && (mb_strlen($legalisir->lokasi_arsip_fisik, 'UTF-8') > 150
                    || preg_match('/[\x00-\x1F\x7F]/', $legalisir->lokasi_arsip_fisik))) {
                throw ValidationException::withMessages(['lokasi_arsip_fisik' => 'Lokasi arsip maksimal 150 karakter dan tidak boleh mengandung karakter kontrol.']);
            }
            $attributes = $legalisir->getAttributes();
            if (! preg_match('/^\d+$/D', (string) ($attributes['jumlah_lembar'] ?? '')) || (int) $legalisir->jumlah_lembar < 1 || (int) $legalisir->jumlah_lembar > 10000) {
                throw ValidationException::withMessages(['jumlah_lembar' => 'Jumlah lembar harus antara 1 dan 10.000.']);
            }
            if (! is_numeric($attributes['biaya'] ?? null) || (float) $legalisir->biaya < 0 || (float) $legalisir->biaya > 9_999_999_999.99) {
                throw ValidationException::withMessages(['biaya' => 'Biaya harus berupa angka 0 sampai 9.999.999.999,99.']);
            }
            if ($legalisir->tahun_lulus !== null && ((int) $legalisir->tahun_lulus < 1900 || (int) $legalisir->tahun_lulus > (int) date('Y'))) {
                throw ValidationException::withMessages(['tahun_lulus' => 'Tahun lulus tidak valid.']);
            }

            $applicationDate = Carbon::parse($legalisir->tanggal_permohonan);
            foreach (['tanggal_selesai', 'tanggal_diambil', 'jadwal_pengambilan'] as $field) {
                if ($legalisir->{$field} && Carbon::parse($legalisir->{$field})->lt($applicationDate)) {
                    throw ValidationException::withMessages([$field => 'Tanggal tindak lanjut tidak boleh sebelum tanggal permohonan.']);
                }
            }
        });

        static::creating(function (Legalisir $legalisir): void {
            if (! $legalisir->nomor_legalisir) {
                $tahun = Carbon::parse($legalisir->tanggal_permohonan ?? now())->year;
                $legalisir->nomor_legalisir = static::generateNomorLegalisir($tahun);
            }

            if (! $legalisir->nomor_antrean) {
                $legalisir->nomor_antrean = static::generateNomorAntrean($legalisir->tanggal_permohonan);
            }

            if (! $legalisir->tracking_token_hash) {
                $token = Str::random(48);
                $legalisir->tracking_token_hash = hash('sha256', $token);
                $legalisir->tracking_token_encrypted = $token;
            }
        });

        static::updated(function (Legalisir $legalisir): void {
            if ($legalisir->wasChanged('status') && $legalisir->status === 'siap_diambil') {
                $legalisir->antrekanNotifikasiSiapDiambil();
            }
        });

        static::updating(function (Legalisir $legalisir): void {
            if ($legalisir->isDirty(['nomor_legalisir', 'nomor_antrean'])) {
                throw ValidationException::withMessages(['nomor_legalisir' => 'Nomor legalisir dan antrean tidak dapat diubah setelah tersimpan.']);
            }

            if (preg_match('/^LEG\/(\d{4})\//', (string) $legalisir->nomor_legalisir, $matches)
                && (int) $matches[1] !== Carbon::parse($legalisir->tanggal_permohonan)->year) {
                throw ValidationException::withMessages(['tanggal_permohonan' => 'Tahun permohonan harus sesuai dengan nomor legalisir.']);
            }
        });
    }

    public static function generateNomorAntrean($tanggal = null): string
    {
        $tanggal = $tanggal ? Carbon::parse($tanggal) : now();
        $prefix = 'L-'.$tanggal->format('Ymd').'-';
        $pattern = '/^'.preg_quote($prefix, '/').'(\d+)$/';
        $currentMaximum = static::withTrashed()
            ->where('nomor_antrean', 'like', $prefix.'%')
            ->pluck('nomor_antrean')
            ->reduce(function (int $maximum, string $number) use ($pattern): int {
                return preg_match($pattern, $number, $matches)
                    ? max($maximum, (int) $matches[1])
                    : $maximum;
            }, 0);
        $urut = DocumentSequence::next('legalisir_antrean:'.$tanggal->format('Ymd'), $currentMaximum);

        return $prefix.str_pad((string) $urut, 3, '0', STR_PAD_LEFT);
    }

    public function trackingUrl(): ?string
    {
        return $this->tracking_token_encrypted
            ? route('legalisir.tracking', ['token' => $this->tracking_token_encrypted])
            : null;
    }

    public function checklistLengkap(): bool
    {
        return $this->dokumen_asli_dilihat
            && $this->identitas_cocok
            && $this->data_sekolah_cocok;
    }

    public function antrekanNotifikasiSiapDiambil(): void
    {
        $kanal = match ($this->kanal_notifikasi) {
            'keduanya' => ['email', 'whatsapp'],
            'email', 'whatsapp' => [$this->kanal_notifikasi],
            default => [],
        };

        foreach ($kanal as $jenis) {
            $tujuan = $jenis === 'email' ? $this->email_pemohon : $this->kontak;
            if (! $tujuan || ! NotificationOutbox::destinationIsValid($jenis, $tujuan)) {
                continue;
            }

            NotificationOutbox::firstOrCreate([
                'entitas' => 'Legalisir',
                'entitas_id' => $this->getKey(),
                'kanal' => $jenis,
                'tujuan' => $tujuan,
                'template' => 'legalisir_siap_diambil',
            ], [
                'payload' => [
                    'nomor_antrean' => $this->nomor_antrean,
                    'status' => self::STATUSES[$this->status] ?? $this->status,
                    'tracking_url' => $this->trackingUrl(),
                ],
            ]);
        }
    }

    public static function generateNomorLegalisir(?int $tahun = null): string
    {
        $tahun = $tahun ?? (int) date('Y');
        $pattern = '/^LEG\/'.preg_quote((string) $tahun, '/').'\/(\d+)$/';
        $currentMaximum = static::withTrashed()
            ->where('nomor_legalisir', 'like', "LEG/{$tahun}/%")
            ->pluck('nomor_legalisir')
            ->reduce(function (int $maximum, string $number) use ($pattern): int {
                return preg_match($pattern, $number, $matches)
                    ? max($maximum, (int) $matches[1])
                    : $maximum;
            }, 0);
        $next = DocumentSequence::next("legalisir:{$tahun}", $currentMaximum);

        return "LEG/{$tahun}/".str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    public function scopePerluTindakLanjut(Builder $query): Builder
    {
        return $query->whereIn('status', ['diterima', 'diproses']);
    }

    public function petugas(): BelongsTo
    {
        return $this->belongsTo(User::class, 'petugas_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diverifikasi_oleh');
    }

    public function lampiran(): MorphMany
    {
        return $this->morphMany(Lampiran::class, 'entitas', 'entitas', 'entitas_id');
    }
}
