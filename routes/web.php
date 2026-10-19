<?php

use App\Http\Controllers\LegalisirPrintController;
use App\Http\Controllers\LegalisirTrackingController;
use App\Http\Controllers\PrivateDocumentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return redirect('/admin');
});

Route::get('/lacak-legalisir/{token}', [LegalisirTrackingController::class, 'show'])
    ->where('token', '[A-Za-z0-9]{32,}')
    ->name('legalisir.tracking');

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/legalisir/{id}/cetak-bukti', [LegalisirPrintController::class, 'cetakBukti'])
        ->name('legalisir.cetak-bukti');
    Route::get('/lampiran/{lampiran}/download', [PrivateDocumentController::class, 'download'])
        ->name('lampiran.download');
    Route::get('/lampiran/{lampiran}/preview', [PrivateDocumentController::class, 'preview'])
        ->name('lampiran.preview');
    Route::get('/profil/pegawai/{pegawai}/foto', [PrivateDocumentController::class, 'fotoPegawai'])
        ->name('profil.pegawai.foto');
    Route::get('/profil/pengguna/{user}/foto', [PrivateDocumentController::class, 'fotoPengguna'])
        ->name('profil.pengguna.foto');
});
