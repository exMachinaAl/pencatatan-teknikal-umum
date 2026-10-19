<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Status Legalisir {{ $legalisir->nomor_antrean }}</title>
    <style>
        :root { color-scheme: light; --ink:#17221d; --muted:#66736d; --line:#d9dfdc; --paper:#f4f6f5; --green:#1f5d46; --soft:#e6efe9; }
        * { box-sizing:border-box; }
        body { margin:0; background:var(--paper); color:var(--ink); font:16px/1.55 ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif; }
        main { width:min(720px,calc(100% - 32px)); margin:48px auto; }
        .hero { padding:32px; background:var(--green); color:white; border-radius:18px 18px 0 0; }
        .eyebrow { margin:0 0 8px; font-size:12px; font-weight:700; letter-spacing:.12em; text-transform:uppercase; opacity:.78; }
        h1 { margin:0; font-size:clamp(26px,5vw,40px); line-height:1.15; }
        .panel { background:white; border:1px solid var(--line); border-top:0; border-radius:0 0 18px 18px; padding:28px; }
        .status { display:inline-flex; padding:8px 12px; border-radius:999px; background:var(--soft); color:var(--green); font-weight:700; }
        dl { display:grid; grid-template-columns:1fr 1fr; gap:18px 28px; margin:28px 0; }
        dt { color:var(--muted); font-size:13px; } dd { margin:3px 0 0; font-weight:650; }
        .verify { display:flex; gap:24px; align-items:center; padding-top:24px; border-top:1px solid var(--line); }
        .verify img { width:132px; height:132px; border:1px solid var(--line); border-radius:10px; }
        .note { color:var(--muted); font-size:14px; }
        @media (max-width:560px) { main{margin:16px auto}.hero,.panel{padding:22px}dl{grid-template-columns:1fr}.verify{align-items:flex-start}.verify img{width:104px;height:104px} }
    </style>
</head>
<body>
<main>
    <section class="hero">
        <p class="eyebrow">SIPATU · Pelacakan resmi</p>
        <h1>{{ $legalisir->nomor_antrean }}</h1>
    </section>
    <section class="panel">
        <span class="status">{{ \App\Models\Legalisir::STATUSES[$legalisir->status] ?? $legalisir->status }}</span>
        <dl>
            <div><dt>Nomor registrasi</dt><dd>{{ $legalisir->nomor_legalisir }}</dd></div>
            <div><dt>Tanggal permohonan</dt><dd>{{ $legalisir->tanggal_permohonan?->translatedFormat('d F Y') }}</dd></div>
            <div><dt>Jenis dokumen</dt><dd>{{ $legalisir->jenis_dokumen }}</dd></div>
            <div><dt>Jumlah</dt><dd>{{ $legalisir->jumlah_lembar }} lembar</dd></div>
            @if($legalisir->jadwal_pengambilan)
                <div><dt>Jadwal pengambilan</dt><dd>{{ $legalisir->jadwal_pengambilan->translatedFormat('d F Y, H:i') }}</dd></div>
            @endif
        </dl>
        <div class="verify">
            <img src="{{ $qrDataUri }}" alt="QR verifikasi status legalisir">
            <div>
                <strong>Verifikasi tanpa data pribadi</strong>
                <p class="note">Halaman ini hanya menampilkan nomor layanan, status, jenis dokumen, dan jadwal. Nama, kontak, serta nomor dokumen tidak dipublikasikan.</p>
            </div>
        </div>
    </section>
</main>
</body>
</html>
