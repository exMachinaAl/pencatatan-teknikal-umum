<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Legalisir;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class LegalisirPrintController extends Controller
{
    public function cetakBukti(int $id)
    {
        $legalisir = Legalisir::with('petugas')->findOrFail($id);

        $schoolName = AppSetting::get('nama_sekolah', 'SMAN 1 BINA BANGSA');
        $schoolAddress = AppSetting::get('alamat_sekolah', 'Jl. Pemuda Pendidikan No. 45, Bandung');
        $schoolPhone = AppSetting::get('telepon_sekolah', '(022) 7654321');
        $schoolEmail = AppSetting::get('email_sekolah', 'tu@sman1binabangsa.sch.id');

        $pdf = Pdf::loadView('pdf.bukti-legalisir', compact(
            'legalisir',
            'schoolName',
            'schoolAddress',
            'schoolPhone',
            'schoolEmail'
        ))->setPaper('a5', 'portrait');

        $filename = "bukti-legalisir-{$legalisir->nomor_legalisir}.pdf";
        $filename = str_replace(['/', '\\'], '-', $filename);

        return $pdf->stream($filename);
    }
}
