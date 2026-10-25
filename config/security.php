<?php

return [
    'backup_archive_password' => env('BACKUP_ARCHIVE_PASSWORD'),

    'initial_passwords' => [
        'admin' => env('TU_ADMIN_SISTEM_INITIAL_PASSWORD'),
        'kepala_tu' => env('TU_KEPALA_TU_INITIAL_PASSWORD'),
        'kepala_sekolah' => env('TU_KEPALA_SEKOLAH_INITIAL_PASSWORD'),
        'staf_tu' => env('TU_PETUGAS_TU_INITIAL_PASSWORD'),
        'pegawai' => env('TU_PEGAWAI_INITIAL_PASSWORD'),
    ],
];
