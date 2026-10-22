@php
    $entity = class_basename($record);
    $timeline = \App\Models\AuditLog::query()
        ->where('entitas', $entity)
        ->where('entitas_id', $record->getKey())
        ->latest('created_at')->limit(30)->get();
    $attachments = \App\Models\Lampiran::query()
        ->where('entitas', $entity)
        ->where('entitas_id', $record->getKey())
        ->latest('versi')->get();
@endphp

<div class="grid gap-5 xl:grid-cols-2">
    @if($record instanceof \App\Models\Legalisir)
        <x-filament::section heading="Pelacakan & verifikasi" icon="heroicon-o-qr-code">
            <dl class="grid gap-4 sm:grid-cols-2">
                <div><dt class="text-xs text-gray-500">Nomor antrean</dt><dd class="font-semibold text-gray-950 dark:text-white">{{ $record->nomor_antrean ?: 'Belum tersedia' }}</dd></div>
                <div><dt class="text-xs text-gray-500">Checklist</dt><dd class="font-semibold {{ $record->checklistLengkap() ? 'text-success-700' : 'text-warning-700' }}">{{ $record->checklistLengkap() ? 'Lengkap' : 'Belum lengkap' }}</dd></div>
            </dl>
            @if($record->trackingUrl())
                <div class="mt-4 break-all rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                    <p class="text-xs text-gray-500">Tautan aman untuk pemohon</p>
                    <a class="mt-1 block text-sm font-medium text-primary-700 hover:underline dark:text-primary-300" href="{{ $record->trackingUrl() }}" target="_blank">{{ $record->trackingUrl() }}</a>
                </div>
            @endif
        </x-filament::section>
    @endif

    @if($record instanceof \App\Models\SuratMasuk)
        <x-filament::section heading="Disposisi & tenggat" icon="heroicon-o-clock">
            <div class="space-y-3">
                @forelse($record->disposisi()->with('penanggungJawab')->latest()->get() as $item)
                    <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="font-medium text-gray-950 dark:text-white">{{ $item->penanggungJawab?->name ?: $item->kepada }}</span>
                            <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $item->is_terlambat ? 'bg-danger-50 text-danger-700' : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300' }}">{{ $item->is_terlambat ? 'Terlambat' : (\App\Models\Disposisi::STATUSES[$item->status] ?? $item->status) }}</span>
                        </div>
                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">{{ $item->instruksi ?: 'Tanpa instruksi tambahan' }}</p>
                        <p class="mt-2 text-xs text-gray-500">Tenggat: {{ $item->batas_waktu?->translatedFormat('d M Y') ?: 'tidak ditentukan' }}</p>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">Belum ada disposisi.</p>
                @endforelse
            </div>
        </x-filament::section>
    @endif

    @if($record instanceof \App\Models\SuratKeluar)
        <x-filament::section heading="Pengiriman" icon="heroicon-o-truck">
            <dl class="grid gap-4 sm:grid-cols-2">
                <div><dt class="text-xs text-gray-500">Metode</dt><dd class="font-medium text-gray-950 dark:text-white">{{ $record->metode_pengiriman ?: '-' }}</dd></div>
                <div><dt class="text-xs text-gray-500">Nomor resi</dt><dd class="font-medium text-gray-950 dark:text-white">{{ $record->nomor_resi ?: '-' }}</dd></div>
                <div><dt class="text-xs text-gray-500">Dikirim</dt><dd class="font-medium text-gray-950 dark:text-white">{{ $record->dikirim_pada?->translatedFormat('d M Y, H:i') ?: '-' }}</dd></div>
                <div><dt class="text-xs text-gray-500">Diterima</dt><dd class="font-medium text-gray-950 dark:text-white">{{ $record->diterima_pada?->translatedFormat('d M Y, H:i') ?: '-' }}</dd></div>
            </dl>
        </x-filament::section>
    @endif

    @php
        $related = $record instanceof \App\Models\SuratMasuk
            ? $record->suratBalasan()->get()
            : ($record instanceof \App\Models\SuratKeluar ? $record->suratMasukTerkait()->get() : collect());
    @endphp
    @if($record instanceof \App\Models\SuratMasuk || $record instanceof \App\Models\SuratKeluar)
        <x-filament::section heading="Surat terkait" icon="heroicon-o-link">
            <div class="space-y-2">
                @forelse($related as $item)
                    @php($url = $item instanceof \App\Models\SuratMasuk ? \App\Filament\Resources\SuratMasukResource::getUrl('view', ['record' => $item]) : \App\Filament\Resources\SuratKeluarResource::getUrl('view', ['record' => $item]))
                    <a href="{{ $url }}" class="block rounded-xl border border-gray-200 p-3 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800">
                        <span class="font-medium text-gray-950 dark:text-white">{{ $item->nomor_agenda ?? $item->nomor_surat }}</span>
                        <span class="mt-1 block truncate text-sm text-gray-500">{{ $item instanceof \App\Models\SuratMasuk ? $item->pengirim : $item->tujuan }}</span>
                    </a>
                @empty
                    <p class="text-sm text-gray-500">Belum ada surat yang dihubungkan.</p>
                @endforelse
            </div>
        </x-filament::section>
    @endif

    <x-filament::section heading="Lampiran privat & versi" icon="heroicon-o-paper-clip">
        <div class="space-y-2">
            @forelse($attachments as $attachment)
                <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
                    @if($attachment->is_current && $attachment->is_image)
                        <a href="{{ route('lampiran.preview', $attachment) }}" target="_blank" rel="noopener" class="block bg-gray-50 p-2 dark:bg-gray-900">
                            <img
                                src="{{ route('lampiran.preview', $attachment) }}"
                                alt="Preview {{ $attachment->nama_file }}"
                                class="mx-auto max-h-56 w-full rounded-lg object-contain"
                                loading="lazy"
                            >
                        </a>
                    @endif
                    <div class="flex flex-wrap items-center justify-between gap-3 p-3">
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-medium text-gray-950 dark:text-white">{{ $attachment->nama_file }}</span>
                            <span class="text-xs text-gray-500">{{ $attachment->keterangan }} · Versi {{ $attachment->versi }} · {{ $attachment->ukuran_formatted }}</span>
                        </span>
                        <span class="shrink-0 rounded-full px-2 py-1 text-xs {{ $attachment->is_current ? 'bg-success-50 text-success-700' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300' }}">{{ $attachment->is_current ? 'Aktif' : 'Arsip' }}</span>
                        <span class="flex shrink-0 gap-2">
                            @if($attachment->is_previewable)
                                <a href="{{ route('lampiran.preview', $attachment) }}" target="_blank" rel="noopener" class="text-sm font-semibold text-primary-700 hover:underline dark:text-primary-300">Preview</a>
                            @endif
                            <a href="{{ route('lampiran.download', $attachment) }}" class="text-sm font-semibold text-gray-700 hover:underline dark:text-gray-200">Unduh</a>
                        </span>
                    </div>
                </div>
            @empty
                <p class="text-sm text-gray-500">Belum ada lampiran yang tercatat.</p>
            @endforelse
        </div>
    </x-filament::section>

    <x-filament::section heading="Timeline aktivitas" icon="heroicon-o-list-bullet">
        <ol class="space-y-4">
            @forelse($timeline as $event)
                <li class="border-l-2 border-gray-200 pl-4 dark:border-gray-700">
                    <p class="font-medium text-gray-950 dark:text-white">{{ str($event->aksi)->replace('_', ' ')->title() }}</p>
                    <p class="mt-1 text-sm text-gray-500">{{ $event->user_name ?: 'Sistem' }} · {{ $event->created_at?->translatedFormat('d M Y, H:i') }}</p>
                    @if($event->data_sesudah)
                        <p class="mt-1 line-clamp-2 text-xs text-gray-500">{{ collect($event->data_sesudah)->except(['tracking_token_hash', 'tracking_token_encrypted'])->keys()->implode(', ') }}</p>
                    @endif
                </li>
            @empty
                <p class="text-sm text-gray-500">Belum ada aktivitas tercatat.</p>
            @endforelse
        </ol>
    </x-filament::section>
</div>
