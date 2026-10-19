<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <p class="mb-4 text-sm text-gray-600 dark:text-gray-300">
                <strong>V3.1 — Kepegawaian.</strong> Download template, isi data, lalu periksa pratinjau sebelum menyimpan.
                Data lama tidak akan diubah. Impor KP dan KGB memerlukan pegawai yang sudah ada.
            </p>
            <form wire:submit="previewImport" class="space-y-4">
                {{ $this->form }}
                <div class="flex flex-wrap gap-3 pt-3">
                    <x-filament::button type="button" wire:click="downloadTemplate" icon="heroicon-o-document-arrow-down" color="gray">Download Template</x-filament::button>
                    <x-filament::button type="button" wire:click="downloadExport" icon="heroicon-o-arrow-down-tray">Export Sesuai Filter</x-filament::button>
                    @if (\App\Services\TuPersonnelExchange::authorized(auth()->user()?->role ?? '', $data['module'] ?? '', true))
                        <x-filament::button type="submit" icon="heroicon-o-magnifying-glass-circle" color="info">Pratinjau Import</x-filament::button>
                    @endif
                </div>
            </form>
            <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                Hak akses: Admin dapat impor/ekspor semua modul; Staf hanya Pegawai &amp; KP; Kepala hanya ekspor. Nominal KGB tidak boleh diakses Staf.
            </p>
        </div>

        @if ($preview)
            <div class="space-y-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <h2 class="font-bold text-gray-900 dark:text-white">Pratinjau: {{ \App\Services\TuPersonnelExchange::modules(auth()->user()?->role ?? '')[$previewModule] ?? '' }}</h2>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    @foreach (['total'=>'Total baris','valid'=>'Data baru','duplicates'=>'Duplikat','invalid'=>'Tidak valid'] as $field=>$label)
                        <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ $label }}</div>
                            <div class="text-xl font-bold text-gray-900 dark:text-white">{{ $preview[$field] ?? 0 }}</div>
                        </div>
                    @endforeach
                </div>
                @if (count($preview['errors'] ?? []))
                    <div class="rounded-lg border border-red-300 p-3 text-sm text-red-700 dark:border-red-700 dark:text-red-400">
                        <strong>Kesalahan:</strong>
                        <ul class="mt-2 list-inside list-disc break-words">@foreach($preview['errors'] as $message)<li>{{ $message }}</li>@endforeach</ul>
                    </div>
                @endif
                @if (count($preview['duplicate_messages'] ?? []))
                    <div class="rounded-lg border border-amber-300 p-3 text-sm text-amber-700 dark:border-amber-700 dark:text-amber-400">
                        <strong>Duplikat yang dilewati:</strong>
                        <ul class="mt-2 list-inside list-disc break-words">@foreach($preview['duplicate_messages'] as $message)<li>{{ $message }}</li>@endforeach</ul>
                    </div>
                @endif
                @if (count($preview['preview'] ?? []))
                    <div class="max-w-full overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                        <table class="min-w-full divide-y divide-gray-200 text-left text-xs dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-800"><tr>@foreach($preview['columns'] as $col)<th class="whitespace-nowrap px-3 py-2">{{ $col }}</th>@endforeach</tr></thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">@foreach($preview['preview'] as $row)<tr>@foreach($row as $cell)<td class="max-w-xs break-words px-3 py-2">{{ $cell }}</td>@endforeach</tr>@endforeach</tbody>
                        </table>
                    </div>
                @endif
                <p class="text-xs text-gray-500 dark:text-gray-400">Hanya 5 contoh baris. Nominal gaji disembunyikan pada pratinjau. Impor menambah data saja, tidak memperbarui baris lama.</p>
                @if (($preview['invalid'] ?? 0)===0 && ($preview['valid'] ?? 0)>0)
                    <x-filament::button wire:click="commitImport" icon="heroicon-o-check-circle" color="success">Konfirmasi Import {{ $preview['valid'] }} Baris</x-filament::button>
                @endif
            </div>
        @endif

        @if ($result)
            <div class="rounded-lg border border-green-300 bg-white p-4 text-sm dark:border-green-700 dark:bg-gray-900">
                <strong>Import selesai.</strong> {{ $result['success'] }} record baru; {{ $result['skipped'] }} dilewati dari {{ $result['total'] }} baris.
            </div>
        @endif
    </div>
</x-filament-panels::page>
