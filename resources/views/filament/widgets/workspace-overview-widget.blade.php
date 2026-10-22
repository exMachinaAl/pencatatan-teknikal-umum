<x-filament-widgets::widget>
    <div class="tu-dashboard">
        <header class="tu-hero">
            <div class="tu-hero-copy">
                <p class="tu-hero-kicker">Sistem Administrasi Sekolah</p>
                <h1>Selamat datang, {{ auth()->user()?->name ?? 'Petugas Tata Usaha' }}</h1>
                <p>{{ $roleLabel }} · Menu dan data ditampilkan sesuai tanggung jawab Anda.</p>

                @if ($canCreateSuratMasuk || $canCreateSuratKeluar)
                <div class="tu-hero-actions">
                    @if ($canCreateSuratMasuk)
                    <a href="{{ \App\Filament\Resources\SuratMasukResource::getUrl('create') }}" class="tu-hero-button tu-hero-button--primary">
                        <x-filament::icon icon="heroicon-m-plus" />
                        Catat surat masuk
                    </a>
                    @endif
                    @if ($canCreateSuratKeluar)
                    <a href="{{ \App\Filament\Resources\SuratKeluarResource::getUrl('create') }}" class="tu-hero-button tu-hero-button--secondary">
                        Buat surat keluar
                        <x-filament::icon icon="heroicon-m-arrow-right" />
                    </a>
                    @endif
                </div>
                @endif
            </div>

            <div class="tu-hero-date" aria-label="Tanggal hari ini">
                <x-filament::icon icon="heroicon-o-calendar-days" />
                <span>Hari ini</span>
                <strong>{{ now('Asia/Jakarta')->locale('id')->translatedFormat('l, d F Y') }}</strong>
            </div>
        </header>

        @if ($canViewSurat)
        <section class="tu-priority" aria-labelledby="priority-title">
            <div class="tu-section-heading">
                <div>
                    <p class="tu-eyebrow">Prioritas terbaru</p>
                    <h2 id="priority-title">Surat yang perlu dilihat</h2>
                </div>
                <span>Maksimal 2 item</span>
            </div>

            <div class="tu-priority-grid">
                <article class="tu-letter-card tu-letter-card--incoming">
                    <div class="tu-letter-topline">
                        <span class="tu-letter-type">Surat masuk</span>
                        @if ($suratMasuk)
                            <span class="tu-status-dot">{{ \App\Models\SuratMasuk::STATUSES[$suratMasuk->status] ?? $suratMasuk->status }}</span>
                        @endif
                    </div>

                    @if ($suratMasuk)
                        <p class="tu-letter-number">{{ $suratMasuk->nomor_agenda }}</p>
                        <h3>{{ $suratMasuk->pengirim }}</h3>
                        <p class="tu-letter-meta">
                            {{ $suratMasuk->klasifikasi?->kode ? 'Klasifikasi '.$suratMasuk->klasifikasi->kode : 'Tanpa klasifikasi' }}
                            <span aria-hidden="true">·</span>
                            {{ $suratMasuk->tanggal_diterima?->translatedFormat('d M Y') }}
                        </p>
                        <a href="{{ \App\Filament\Resources\SuratMasukResource::getUrl('view', ['record' => $suratMasuk]) }}" class="tu-text-link">
                            Buka surat
                            <x-filament::icon icon="heroicon-m-arrow-up-right" />
                        </a>
                    @else
                        <h3>Tidak ada surat masuk yang menunggu</h3>
                        <p class="tu-letter-meta">Surat selesai dan arsip tetap tersedia di daftar surat.</p>
                        <a href="{{ \App\Filament\Resources\SuratMasukResource::getUrl() }}" class="tu-text-link">Lihat semua surat masuk</a>
                    @endif
                </article>

                <article class="tu-letter-card tu-letter-card--outgoing">
                    <div class="tu-letter-topline">
                        <span class="tu-letter-type">Surat keluar</span>
                        @if ($suratKeluar)
                            <span class="tu-status-dot">{{ \App\Models\SuratKeluar::STATUSES[$suratKeluar->status] ?? $suratKeluar->status }}</span>
                        @endif
                    </div>

                    @if ($suratKeluar)
                        <p class="tu-letter-number">{{ $suratKeluar->nomor_surat }}</p>
                        <h3>{{ $suratKeluar->tujuan }}</h3>
                        <p class="tu-letter-meta">
                            {{ $suratKeluar->klasifikasi?->kode ? 'Klasifikasi '.$suratKeluar->klasifikasi->kode : 'Tanpa klasifikasi' }}
                            <span aria-hidden="true">·</span>
                            {{ $suratKeluar->tanggal_surat?->translatedFormat('d M Y') }}
                        </p>
                        <a href="{{ \App\Filament\Resources\SuratKeluarResource::getUrl('view', ['record' => $suratKeluar]) }}" class="tu-text-link">
                            Buka surat
                            <x-filament::icon icon="heroicon-m-arrow-up-right" />
                        </a>
                    @else
                        <h3>Tidak ada konsep yang menunggu</h3>
                        <p class="tu-letter-meta">Surat terkirim dan arsip tetap tersedia di daftar surat.</p>
                        <a href="{{ \App\Filament\Resources\SuratKeluarResource::getUrl() }}" class="tu-text-link">Lihat semua surat keluar</a>
                    @endif
                </article>
            </div>
        </section>
        @endif

        @if (count($modules))
        <section class="tu-quick-nav" aria-labelledby="quick-nav-title">
            <div class="tu-section-heading">
                <div>
                    <p class="tu-eyebrow">Navigasi utama</p>
                    <h2 id="quick-nav-title">Pilih ruang kerja</h2>
                </div>
                <span>Administrasi harian</span>
            </div>

            <nav class="tu-module-list" aria-label="Menu modul tata usaha">
                @foreach ($modules as $module)
                    <a href="{{ $module['url'] }}" class="tu-module-link">
                        <span class="tu-module-icon">
                            <x-filament::icon :icon="$module['icon']" />
                        </span>
                        <span class="tu-module-copy">
                            <strong>{{ $module['label'] }}</strong>
                            <small>{{ $module['description'] }}</small>
                        </span>
                        <x-filament::icon icon="heroicon-m-chevron-right" class="tu-chevron" />
                    </a>
                @endforeach
            </nav>
        </section>
        @endif

        @if (count($attention))
        <section class="tu-attention" aria-labelledby="attention-title">
            <div class="tu-section-heading tu-section-heading--compact">
                <div>
                    <p class="tu-eyebrow">Tindak lanjut</p>
                    <h2 id="attention-title">Perlu perhatian</h2>
                </div>
            </div>

            <div class="tu-attention-list">
                @foreach ($attention as $item)
                    <a href="{{ $item['url'] }}" class="tu-attention-row">
                        <span class="tu-attention-marker tu-attention-marker--{{ $item['tone'] }}"></span>
                        <span class="tu-attention-copy">
                            <strong>{{ $item['label'] }}</strong>
                            <small>{{ $item['detail'] }}</small>
                        </span>
                        <span class="tu-attention-count">{{ $item['count'] }}</span>
                        <x-filament::icon icon="heroicon-m-chevron-right" class="tu-chevron" />
                    </a>
                @endforeach
            </div>
        </section>
        @endif

        @if (count($utilities))
            <details class="tu-utilities">
                <summary>Pengaturan dan utilitas</summary>
                <nav aria-label="Menu pengaturan dan utilitas">
                    @foreach ($utilities as $utility)
                        <a class="tu-text-link" href="{{ $utility['url'] }}">
                            {{ $utility['label'] }}
                            <x-filament::icon icon="heroicon-m-arrow-up-right" />
                        </a>
                    @endforeach
                </nav>
            </details>
        @endif
    </div>
</x-filament-widgets::widget>
