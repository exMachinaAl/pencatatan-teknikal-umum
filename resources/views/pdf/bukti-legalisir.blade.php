<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Bukti Tanda Terima Legalisir - {{ $legalisir->nomor_legalisir }}</title>
    <style>
        @page {
            margin: 20px 25px;
        }
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 11px;
            color: #333;
            line-height: 1.4;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #222;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }
        .header h2 {
            margin: 0;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .header h1 {
            margin: 2px 0;
            font-size: 16px;
            font-weight: bold;
            color: #047857;
            text-transform: uppercase;
        }
        .header p {
            margin: 0;
            font-size: 9px;
            color: #555;
        }
        .title-box {
            text-align: center;
            background-color: #f3f4f6;
            border: 1px dashed #9ca3af;
            padding: 6px;
            margin-bottom: 12px;
            border-radius: 4px;
        }
        .title-box h3 {
            margin: 0;
            font-size: 12px;
            text-transform: uppercase;
            color: #111;
        }
        .title-box .reg-no {
            font-size: 12px;
            font-weight: bold;
            color: #047857;
            margin-top: 2px;
            font-family: 'Courier New', monospace;
        }
        table.info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        table.info-table td {
            padding: 4px 6px;
            vertical-align: top;
        }
        table.info-table td.label {
            width: 32%;
            font-weight: bold;
            color: #4b5563;
        }
        table.info-table td.separator {
            width: 3%;
            text-align: center;
        }
        table.info-table td.value {
            width: 65%;
            color: #111;
        }
        .notice {
            background-color: #ecfdf5;
            border: 1px solid #a7f3d0;
            border-radius: 4px;
            padding: 8px;
            font-size: 9px;
            color: #065f46;
            margin-bottom: 15px;
        }
        .notice ul {
            margin: 4px 0 0 0;
            padding-left: 15px;
        }
        .signatures {
            width: 100%;
            margin-top: 15px;
        }
        .signatures td {
            width: 50%;
            text-align: center;
            vertical-align: top;
            font-size: 10px;
        }
        .sig-space {
            height: 45px;
        }
        .sig-name {
            font-weight: bold;
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <!-- KOP RESMI SEKOLAH -->
    <div class="header">
        <h2>PEMERINTAH PROVINSI JAWA BARAT - DINAS PENDIDIKAN</h2>
        <h1>{{ $schoolName }}</h1>
        <p>{{ $schoolAddress }} | Telp: {{ $schoolPhone }} | Email: {{ $schoolEmail }}</p>
    </div>

    <!-- JUDUL DAN NOMOR REGISTRASI -->
    <div class="title-box">
        <h3>TANDA TERIMA PERMOHONAN LEGALISIR</h3>
        <div class="reg-no">{{ $legalisir->nomor_legalisir }}</div>
    </div>

    <!-- DETAIL INFORMASI -->
    <table class="info-table">
        <tr>
            <td class="label">Tanggal Permohonan</td>
            <td class="separator">:</td>
            <td class="value">{{ $legalisir->tanggal_permohonan?->translatedFormat('d F Y') }}</td>
        </tr>
        <tr>
            <td class="label">Nama Pemohon</td>
            <td class="separator">:</td>
            <td class="value"><strong>{{ $legalisir->nama_pemohon }}</strong></td>
        </tr>
        <tr>
            <td class="label">Status Pemohon</td>
            <td class="separator">:</td>
            <td class="value">{{ strtoupper($legalisir->status_pemohon) }}</td>
        </tr>
        <tr>
            <td class="label">No. Kontak / WA</td>
            <td class="separator">:</td>
            <td class="value">{{ $legalisir->kontak ?: '-' }}</td>
        </tr>
        <tr>
            <td class="label">Dokumen Legalisir</td>
            <td class="separator">:</td>
            <td class="value"><strong>{{ $legalisir->jenis_dokumen }}</strong></td>
        </tr>
        <tr>
            <td class="label">Jumlah Rangkap</td>
            <td class="separator">:</td>
            <td class="value">{{ $legalisir->jumlah_lembar }} Lembar</td>
        </tr>
        <tr>
            <td class="label">Biaya Administrasi</td>
            <td class="separator">:</td>
            <td class="value">
                @if($legalisir->biaya > 0)
                    Rp {{ number_format($legalisir->biaya, 0, ',', '.') }}
                @else
                    <strong>GRATIS (Rp 0)</strong>
                @endif
            </td>
        </tr>
        <tr>
            <td class="label">Status Saat Ini</td>
            <td class="separator">:</td>
            <td class="value">{{ \App\Models\Legalisir::STATUSES[$legalisir->status] ?? $legalisir->status }}</td>
        </tr>
        @if($legalisir->catatan)
        <tr>
            <td class="label">Catatan Tambahan</td>
            <td class="separator">:</td>
            <td class="value">{{ $legalisir->catatan }}</td>
        </tr>
        @endif
    </table>

    <!-- KETENTUAN PENGAMBILAN -->
    <div class="notice">
        <strong>Ketentuan Pengambilan Dokumen:</strong>
        <ul>
            <li>Harap membawa lembar bukti tanda terima ini dan dokumen asli saat pengambilan berkas legalisir.</li>
            <li>Pengambilan yang diwakilkan wajib menyertakan surat kuasa bermaterai atau identitas diri keluarga.</li>
            <li>Layanan buka pada hari kerja Senin - Jumat pukul 08.00 - 15.00 WIB di Ruang Tata Usaha.</li>
        </ul>
    </div>

    <!-- TANDA TANGAN -->
    <table class="signatures">
        <tr>
            <td>
                Pemohon,
                <div class="sig-space"></div>
                <div class="sig-name">{{ $legalisir->nama_pemohon }}</div>
            </td>
            <td>
                Petugas Pelayanan TU,
                <div class="sig-space"></div>
                <div class="sig-name">{{ $legalisir->petugas?->name ?? 'Petugas Tata Usaha' }}</div>
            </td>
        </tr>
    </table>
</body>
</html>
