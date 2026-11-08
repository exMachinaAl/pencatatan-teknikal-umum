<?php

return [
    // Default hanya mempercayai reverse proxy pada mesin yang sama.
    // Tambahkan IP proxy internal melalui TRUSTED_PROXIES bila diperlukan.
    'proxies' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('TRUSTED_PROXIES', '127.0.0.1,::1')),
    ))),
];
