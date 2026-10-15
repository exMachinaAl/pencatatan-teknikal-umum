<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportGformLog extends Model
{
    use HasFactory;

    protected $table = 'import_gform_log';

    protected $fillable = [
        'modul',
        'nama_file',
        'baris_total',
        'baris_berhasil',
        'baris_gagal',
        'detail_error',
        'imported_by',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }
}
