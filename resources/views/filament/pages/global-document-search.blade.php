<x-filament-panels::page>
    <div class="tu-search-page">
        <section class="tu-search-panel">
            <p class="tu-search-eyebrow">Cari lintas layanan</p>
            <h2>Temukan berkas dari satu tempat</h2>
            <p class="tu-search-description">Cari melalui nomor surat, agenda, pengirim, tujuan, kode klasifikasi, lokasi arsip fisik, nomor antrean, atau nama pemohon.</p>
            <div class="tu-search-input">
                <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass">
                    <x-filament::input
                        type="search"
                        wire:model.live.debounce.350ms="query"
                        placeholder="Ketik minimal 2 karakter…"
                        autofocus
                    />
                </x-filament::input.wrapper>
            </div>
        </section>

        @php($results = $this->getResults())

        @if(mb_strlen(trim($query)) < 2)
            <div class="tu-search-empty tu-search-empty--hint">Masukkan sedikitnya dua karakter untuk mulai mencari.</div>
        @elseif($results->isEmpty())
            <div class="tu-search-empty">
                <strong>Berkas tidak ditemukan</strong>
                <span>Periksa nomor atau gunakan kata yang lebih singkat.</span>
            </div>
        @else
            <section class="tu-search-results" aria-label="Hasil pencarian">
                <header class="tu-search-results-header">
                    <strong>{{ $results->count() }} hasil ditemukan</strong>
                    <span>Pilih salah satu berkas untuk melihat detail</span>
                </header>
                @foreach($results as $result)
                    <a href="{{ $result['url'] }}" class="tu-search-result">
                        <div class="tu-search-result-identity">
                            <span>{{ $result['type'] }}</span>
                            <strong>{{ $result['number'] }}</strong>
                        </div>
                        <div class="tu-search-result-copy">
                            <strong>{{ $result['title'] }}</strong>
                            <span>{{ $result['meta'] }}</span>
                        </div>
                        <span class="tu-search-result-status">{{ $result['status'] }}</span>
                    </a>
                @endforeach
            </section>
        @endif
    </div>
</x-filament-panels::page>
