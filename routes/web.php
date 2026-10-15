<?php

use App\Http\Controllers\LegalisirPrintController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return redirect('/admin');
});

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/legalisir/{id}/cetak-bukti', [LegalisirPrintController::class, 'cetakBukti'])
        ->name('legalisir.cetak-bukti');
});
