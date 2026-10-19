<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class DocumentSequence
{
    public static function next(string $key, int $currentMaximum = 0): int
    {
        return DB::transaction(function () use ($key, $currentMaximum): int {
            DB::table('document_sequences')->insertOrIgnore([
                'sequence_key' => $key,
                'last_value' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $sequence = DB::table('document_sequences')
                ->where('sequence_key', $key)
                ->lockForUpdate()
                ->first();

            $next = max((int) $sequence->last_value, $currentMaximum) + 1;

            DB::table('document_sequences')
                ->where('sequence_key', $key)
                ->update([
                    'last_value' => $next,
                    'updated_at' => now(),
                ]);

            return $next;
        }, 3);
    }
}
