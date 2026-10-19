<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

class Pegawai extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'pegawai';

    protected $fillable = [
        'nip',
        'nama',
        'email',
        'nomor_telepon',
        'tempat_lahir',
        'tanggal_lahir',
        'jenis_kelamin',
        'alamat',
        'status_kepegawaian',
        'jabatan',
        'unit_kerja',
        'pendidikan_terakhir',
        'tanggal_mulai_tugas',
        'foto_path',
        'golongan_id',
        'tmt_pangkat_terakhir',
        'tmt_kgb_terakhir',
        'is_active',
    ];

    protected $casts = [
        'tmt_pangkat_terakhir' => 'date',
        'tmt_kgb_terakhir' => 'date',
        'tanggal_lahir' => 'date',
        'tanggal_mulai_tugas' => 'date',
        'is_active' => 'boolean',
    ];

    public const STATUSES = ['PNS', 'PPPK', 'Honorer'];

    public const JENIS_KELAMIN = [
        'L' => 'Laki-laki',
        'P' => 'Perempuan',
    ];

    protected static function booted(): void
    {
        static::saving(function (Pegawai $pegawai): void {
            $pegawai->nama = trim((string) $pegawai->nama);
            $pegawai->status_kepegawaian ??= 'PNS';
            $pegawai->is_active ??= true;

            if ($pegawai->nama === '' || mb_strlen($pegawai->nama, 'UTF-8') > 150 || preg_match('/[\x00-\x1F\x7F]/', $pegawai->nama)) {
                throw ValidationException::withMessages(['nama' => 'Nama pegawai wajib diisi, maksimal 150 karakter, dan tidak boleh mengandung karakter kontrol.']);
            }

            if (! in_array($pegawai->status_kepegawaian, self::STATUSES, true)) {
                throw ValidationException::withMessages(['status_kepegawaian' => 'Status kepegawaian tidak valid.']);
            }

            if ($pegawai->nip !== null) {
                $pegawai->nip = preg_replace('/\s+/', '', trim((string) $pegawai->nip)) ?: null;
            }

            if ($pegawai->nip !== null) {
                if (! preg_match('/^[A-Za-z0-9.\/-]{1,30}$/D', $pegawai->nip)) {
                    throw ValidationException::withMessages(['nip' => 'Format NIP tidak valid.']);
                }

                $duplicate = static::withTrashed()
                    ->where('nip', $pegawai->nip)
                    ->when($pegawai->exists, fn ($query) => $query->whereKeyNot($pegawai->getKey()))
                    ->exists();

                if ($duplicate) {
                    throw ValidationException::withMessages(['nip' => 'NIP sudah digunakan, termasuk pada data yang dihapus sementara.']);
                }
            }

            if ($pegawai->email !== null) {
                $pegawai->email = mb_strtolower(trim((string) $pegawai->email)) ?: null;
            }

            if ($pegawai->email !== null) {
                if (mb_strlen($pegawai->email, 'UTF-8') > 160 || ! filter_var($pegawai->email, FILTER_VALIDATE_EMAIL)) {
                    throw ValidationException::withMessages(['email' => 'Alamat email pegawai tidak valid.']);
                }

                $emailDipakai = static::withTrashed()
                    ->where('email', $pegawai->email)
                    ->when($pegawai->exists, fn ($query) => $query->whereKeyNot($pegawai->getKey()))
                    ->exists();

                if ($emailDipakai) {
                    throw ValidationException::withMessages(['email' => 'Alamat email pegawai sudah digunakan.']);
                }
            }

            if ($pegawai->nomor_telepon !== null) {
                $pegawai->nomor_telepon = trim((string) $pegawai->nomor_telepon) ?: null;
            }

            if ($pegawai->nomor_telepon !== null && ! preg_match('/^[0-9+().\-\s]{6,30}$/D', $pegawai->nomor_telepon)) {
                throw ValidationException::withMessages(['nomor_telepon' => 'Nomor telepon tidak valid.']);
            }

            if ($pegawai->jenis_kelamin !== null && ! array_key_exists($pegawai->jenis_kelamin, self::JENIS_KELAMIN)) {
                throw ValidationException::withMessages(['jenis_kelamin' => 'Jenis kelamin tidak valid.']);
            }

            if ($pegawai->tanggal_lahir && Carbon::parse($pegawai->tanggal_lahir)->isFuture()) {
                throw ValidationException::withMessages(['tanggal_lahir' => 'Tanggal lahir tidak boleh berada di masa depan.']);
            }
        });
    }

    public function akun(): HasOne
    {
        return $this->hasOne(User::class, 'pegawai_id');
    }

    public function golongan(): BelongsTo
    {
        return $this->belongsTo(RefGolongan::class, 'golongan_id');
    }

    public function kenaikanPangkat(): HasMany
    {
        return $this->hasMany(KenaikanPangkat::class, 'pegawai_id');
    }

    public function gajiBerkala(): HasMany
    {
        return $this->hasMany(GajiBerkala::class, 'pegawai_id');
    }

    public function lampiran(): MorphMany
    {
        return $this->morphMany(Lampiran::class, 'entitas', 'entitas', 'entitas_id');
    }

    // Perkiraan KP Berikutnya (aturan umum: 4 tahun dari TMT pangkat terakhir)
    public function getPerkiraanKpBerikutnyaAttribute(): ?Carbon
    {
        if (! $this->tmt_pangkat_terakhir) {
            return null;
        }

        return Carbon::parse($this->tmt_pangkat_terakhir)->addYears(4);
    }

    // Perkiraan KGB Berikutnya (aturan umum: 2 tahun dari TMT KGB terakhir)
    public function getPerkiraanKgbBerikutnyaAttribute(): ?Carbon
    {
        if (! $this->tmt_kgb_terakhir) {
            return null;
        }

        return Carbon::parse($this->tmt_kgb_terakhir)->addYears(2);
    }

    // Status apakah sudah masuk masa calon kenaikan pangkat (sudah >= 4 tahun atau dalam 6 bulan ke depan)
    public function getIsCalonKpAttribute(): bool
    {
        if (! $this->perkiraan_kp_berikutnya) {
            return false;
        }

        return $this->perkiraan_kp_berikutnya->lte(now()->addMonths(6));
    }

    // Status apakah KGB jatuh tempo dalam 3 bulan ke depan
    public function getIsJatuhTempoKgbAttribute(): bool
    {
        if (! $this->perkiraan_kgb_berikutnya) {
            return false;
        }

        return $this->perkiraan_kgb_berikutnya->lte(now()->addMonths(3));
    }

    public function scopeCalonKp(Builder $query): Builder
    {
        return $query->whereNotNull('tmt_pangkat_terakhir')
            ->whereDate('tmt_pangkat_terakhir', '<=', now()->subYears(4)->addMonths(6)->toDateString());
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopePns(Builder $query): Builder
    {
        return $query->where('status_kepegawaian', 'PNS');
    }

    public function scopePppk(Builder $query): Builder
    {
        return $query->where('status_kepegawaian', 'PPPK');
    }

    public function scopeHonorer(Builder $query): Builder
    {
        return $query->where('status_kepegawaian', 'Honorer');
    }
}
