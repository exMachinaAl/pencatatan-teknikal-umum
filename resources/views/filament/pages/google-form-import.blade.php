<x-filament-panels::page>
    <div class="space-y-6">
        @if ($step === 1)
            <form wire:submit="parseCsv" class="space-y-6">
                {{ $this->form }}

                <div class="flex justify-end">
                    <x-filament::button type="submit" icon="heroicon-o-arrow-right">
                        Lanjut ke Pemetaan Kolom & Pratinjau
                    </x-filament::button>
                </div>
            </form>
        @elseif ($step === 2)
            <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm border border-gray-200 dark:border-gray-700 space-y-6">
                <div class="border-b border-gray-200 dark:border-gray-700 pb-4">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                        <x-filament::icon icon="heroicon-o-arrows-right-left" class="w-5 h-5 text-emerald-600" />
                        Langkah 2: Pemetaan Kolom CSV Google Form ke Database
                    </h3>
                    <p class="text-sm text-gray-500 mt-1">
                        Pilih kolom dari berkas CSV yang sesuai untuk setiap field tujuan pada modul <strong>{{ strtoupper(str_replace('_', ' ', $modul)) }}</strong>.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @foreach ($this->getTargetFields() as $fieldKey => $fieldLabel)
                        <div class="p-3 bg-gray-50 dark:bg-gray-900/50 rounded-lg border border-gray-200 dark:border-gray-700">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                {{ $fieldLabel }}
                            </label>
                            <select wire:model="columnMapping.{{ $fieldKey }}"
                                class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:ring-emerald-500 focus:border-emerald-500">
                                <option value="">-- Abaikan / Jangan Petakan --</option>
                                @foreach ($csvHeaders as $header)
                                    <option value="{{ $header }}">{{ $header }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endforeach
                </div>

                <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                    <h4 class="font-semibold text-sm text-gray-700 dark:text-gray-300 mb-2">Pratinjau 5 Baris Pertama CSV:</h4>
                    <div class="overflow-x-auto border border-gray-200 dark:border-gray-700 rounded-lg">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-xs">
                            <thead class="bg-gray-100 dark:bg-gray-900">
                                <tr>
                                    @foreach ($csvHeaders as $head)
                                        <th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300">{{ $head }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-800">
                                @forelse ($csvPreviewRows as $row)
                                    <tr>
                                        @foreach ($row as $cell)
                                            <td class="px-3 py-2 text-gray-700 dark:text-gray-300 whitespace-nowrap max-w-xs truncate">{{ $cell }}</td>
                                        @endforeach
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ count($csvHeaders) }}" class="p-4 text-center text-gray-400">Tidak ada baris data.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="flex justify-between items-center pt-4 border-t border-gray-200 dark:border-gray-700">
                    <x-filament::button color="gray" wire:click="$set('step', 1)" icon="heroicon-o-arrow-left">
                        Kembali ke Unggah
                    </x-filament::button>

                    <x-filament::button color="success" wire:click="processImport" icon="heroicon-o-check">
                        Jalankan Impor & Deteksi Duplikat
                    </x-filament::button>
                </div>
            </div>
        @elseif ($step === 3)
            <div class="bg-white dark:bg-gray-800 rounded-xl p-8 shadow-sm border border-gray-200 dark:border-gray-700 space-y-6 text-center">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-emerald-100 dark:bg-emerald-900 text-emerald-600 dark:text-emerald-300 mx-auto">
                    <x-filament::icon icon="heroicon-o-check-badge" class="w-10 h-10" />
                </div>

                <h3 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                    Proses Impor Selesai!
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 max-w-xl mx-auto">
                    <div class="bg-blue-50 dark:bg-blue-900/30 p-4 rounded-xl border border-blue-200 dark:border-blue-800">
                        <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ $importResult['total'] ?? 0 }}</div>
                        <div class="text-xs text-gray-500">Total Baris CSV</div>
                    </div>
                    <div class="bg-emerald-50 dark:bg-emerald-900/30 p-4 rounded-xl border border-emerald-200 dark:border-emerald-800">
                        <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ $importResult['success'] ?? 0 }}</div>
                        <div class="text-xs text-gray-500">Berhasil Masuk</div>
                    </div>
                    <div class="bg-amber-50 dark:bg-amber-900/30 p-4 rounded-xl border border-amber-200 dark:border-amber-800">
                        <div class="text-2xl font-bold text-amber-600 dark:text-amber-400">{{ $importResult['failed'] ?? 0 }}</div>
                        <div class="text-xs text-gray-500">Gagal / Duplikat</div>
                    </div>
                </div>

                @if (!empty($importResult['errors']))
                    <div class="text-left max-w-xl mx-auto bg-gray-50 dark:bg-gray-900 p-4 rounded-lg border border-gray-200 dark:border-gray-700">
                        <div class="text-xs font-semibold text-gray-700 dark:text-gray-300 mb-2">Catatan Kesalahan / Duplikasi:</div>
                        <ul class="text-xs text-amber-600 dark:text-amber-400 space-y-1 list-disc list-inside">
                            @foreach (array_slice($importResult['errors'], 0, 10) as $err)
                                <li>{{ $err }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="pt-4 flex justify-center gap-3">
                    <x-filament::button color="gray" wire:click="resetImport" icon="heroicon-o-arrow-path">
                        Impor Berkas Lainnya
                    </x-filament::button>

                    <x-filament::button color="primary" url="{{ route('filament.admin.pages.dashboard') }}" icon="heroicon-o-home">
                        Kembali ke Dashboard
                    </x-filament::button>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
