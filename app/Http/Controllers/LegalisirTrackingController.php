<?php

namespace App\Http\Controllers;

use App\Models\Legalisir;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\View\View;

class LegalisirTrackingController extends Controller
{
    public function show(string $token): View
    {
        abort_unless(strlen($token) >= 32, 404);

        $legalisir = Legalisir::query()
            ->where('tracking_token_hash', hash('sha256', $token))
            ->firstOrFail();

        $qrCode = QrCode::create(route('legalisir.tracking', $token))
            ->setEncoding(new Encoding('UTF-8'))
            ->setErrorCorrectionLevel(ErrorCorrectionLevel::Low)
            ->setSize(220)
            ->setMargin(8)
            ->setRoundBlockSizeMode(RoundBlockSizeMode::Margin);

        $qrDataUri = (new SvgWriter)->write($qrCode)->getDataUri();

        return view('legalisir.tracking', compact('legalisir', 'qrDataUri'));
    }
}
