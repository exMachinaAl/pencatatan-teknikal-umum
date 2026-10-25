<?php

namespace App\Models;

use App\Models\Concerns\HasOptimisticLock;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    use HasFactory, HasOptimisticLock;

    protected $table = 'app_settings';

    protected $fillable = [
        'key',
        'value',
        'deskripsi',
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->first();

        return $setting ? $setting->value : $default;
    }

    public static function set(string $key, mixed $value, ?string $deskripsi = null): static
    {
        return static::updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'deskripsi' => $deskripsi,
            ]
        );
    }
}
