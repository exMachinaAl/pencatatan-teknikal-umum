<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;

trait CreatesApplication
{
    /**
     * Creates the application.
     */
    public function createApplication(): Application
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        $database = $app->make('config')->get('database');

        if (
            ! $app->environment('testing') ||
            ($database['default'] ?? null) !== 'sqlite' ||
            ($database['connections']['sqlite']['database'] ?? null) !== ':memory:'
        ) {
            throw new \RuntimeException(
                'Pengujian dibatalkan: PHPUnit wajib menggunakan database SQLite in-memory.'
            );
        }

        return $app;
    }
}
