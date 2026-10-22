<x-filament-panels::page>
    <div class="space-y-5">
        <section class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900 sm:p-7">
            <p class="text-sm font-medium text-primary-700 dark:text-primary-300">Cari lintas layanan</p>
            <h2 class="mt-1 text-xl font-semibold text-gray-950 dark:text-white">Temukan berkas dari satu tempat</h2>
            <p class="mt-2 max-w-2xl text-sm text-gray-600 dark:text-gray-400">Nomor surat, agenda, pengirim, tujuan, kode klasifikasi, lokasi arsip fisik, nomor antrean, atau nama pemohon.</p>
            <div class="mt-5 max-w-3xl">
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
            <div class="rounded-xl border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500 dark:border-gray-700">Masukkan sedikitnya dua karakter untuk mulai mencari.</div>
        @elseif($results->isEmpty())
            <div class="rounded-xl border border-gray-200 bg-white p-8 text-center dark:border-gray-700 dark:bg-gray-900">
                <p class="font-medium text-gray-900 dark:text-white">Berkas tidak ditemukan</p>
                <p class="mt-1 text-sm text-gray-500">Periksa nomor atau gunakan kata yang lebih singkat.</p>
            </div>
        @else
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                @foreach($results as $result)
                    <a href="{{ $result['url'] }}" class="grid gap-2 border-b border-gray-100 px-5 py-4 last:border-b-0 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-800 sm:grid-cols-[140px_1fr_auto] sm:items-center">
                        <div>
                            <span class="text-xs font-semibold uppercase tracking-wide text-primary-700 dark:text-primary-300">{{ $result['type'] }}</span>
                            <p class="mt-1 text-sm font-medium text-gray-900 dark:text-white">{{ $result['number'] }}</p>
                        </div>
                        <div class="min-w-0">
                            <p class="truncate font-medium text-gray-950 dark:text-white">{{ $result['title'] }}</p>
                            <p class="truncate text-sm text-gray-500">{{ $result['meta'] }}</p>
                        </div>
                        <span class="w-fit rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300">{{ $result['status'] }}</span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-panels::page>
