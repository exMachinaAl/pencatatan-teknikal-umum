<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="mb-3 text-sm text-gray-600 dark:text-gray-300">
                <strong>V3 — Pertukaran Data TU.</strong> Unduh template, isi baris data, dan gunakan pratinjau untuk memeriksa hasil sebelum konfirmasi.
                Data yang ada tidak akan diperbarui atau dihapus oleh impor ini.
            </div>
            <form wire:submit="previewImport" class="space-y-4">
                {{ $this->form }}
                <div class="flex flex-wrap items-center gap-3 pt-3">
                    <x-filament::button type="button" wire:click="downloadTemplate" icon="heroicon-o-document-arrow-down" color="gray">
                        Download Template
                    </x-filament::button>
                    <x-filament::button type="button" wire:click="downloadExport" icon="heroicon-o-arrow-down-tray">
                        Export Sesuai Filter
                    </x-filament::button>
                    @if (in_array(auth()->user()?->role, ['admin','staf'], true))
                        <x-filament::button type="submit" icon="heroicon-o-magnifying-glass-circle" color="info">
                            Periksa File / Preview Import
                        </x-filament::button>
                    @endif
                </div>
            </form>
        </div>

        @if ($preview)
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900 space-y-4">
                <h2 class="text-lg font-bold text-gray-900 dark:text-gray-100">Pratinjau Impor — {{ \App\Services\TuDataExchange::modules()[$previewModule] ?? '' }}</h2>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    @foreach (['total'=>'Baris data','valid'=>'Siap diimpor','duplicates'=>'Duplikat (dilewati)','invalid'=>'Tidak valid'] as $key=>$label)
                        <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                            <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $preview[$key] ?? 0 }}</div>
                            <div class="text-xs text-gray-600 dark:text-gray-300">{{ $label }}</div>
                        </div>
                    @endforeach
                </div>
                @if (!empty($preview['errors']))
                    <div class="rounded-lg border border-red-300 p-3 text-sm text-red-700 dark:text-red-300">
                        <strong>Perbaiki terlebih dahulu:</strong>
                        <ul class="mt-2 list-disc pl-5 space-y-1">
                            @foreach ($preview['errors'] as $error) <li class="break-words">{{ $error }}</li> @endforeach
                        </ul>
                    </div>
                @endif
                @if (!empty($preview['duplicate_messages']))
                    <div class="rounded-lg border border-amber-300 p-3 text-sm text-amber-700 dark:text-amber-300">
                        <strong>Duplikat tidak akan ditambahkan:</strong>
                        <ul class="mt-2 list-disc pl-5 space-y-1">
                            @foreach ($preview['duplicate_messages'] as $warning) <li class="break-words">{{ $warning }}</li> @endforeach
                        </ul>
                    </div>
                @endif
                <div class="max-w-full overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                    <table class="w-full min-w-max text-left text-sm">
                        <thead class="bg-gray-100 dark:bg-gray-800">
                            <tr>@foreach ($preview['columns'] as $col) <th class="px-3 py-2 font-semibold">{{ $col }}</th> @endforeach</tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach ($preview['preview'] as $row)
                                <tr>@foreach ($row as $value) <td class="max-w-xs break-words px-3 py-2">{{ $value }}</td> @endforeach</tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400">Hanya lima contoh baris valid ditampilkan. Data disimpan hanya setelah konfirmasi; file diunggah secara privat.</p>
                <x-filament::button wire:click="commitImport" icon="heroicon-o-check-circle" color="success"
                    :disabled="($preview['invalid'] ?? 0) > 0 || ($preview['valid'] ?? 0) === 0">
                    Konfirmasi Import {{ $preview['valid'] ?? 0 }} Baris
                </x-filament::button>
            </div>
        @endif

        @if ($result)
            <div class="rounded-xl border border-emerald-300 bg-white p-5 text-sm dark:bg-gray-900 dark:border-emerald-700">
                <div class="font-bold text-emerald-700 dark:text-emerald-400">Impor selesai.</div>
                <p class="mt-1">{{ $result['success'] }} data baru ditambahkan dari {{ $result['total'] }} baris; {{ $result['skipped'] }} dilewati. Tidak ada data lama yang ditimpa.</p>
            </div>
        @endif
    </div>
</x-filament-panels::page>
