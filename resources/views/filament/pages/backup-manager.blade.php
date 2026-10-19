<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Banner Action Card -->
        <div class="bg-gradient-to-r from-emerald-600 to-teal-700 text-white rounded-2xl p-6 shadow-md flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <h3 class="text-xl font-bold flex items-center gap-2">
                    <x-filament::icon icon="heroicon-o-shield-check" class="w-6 h-6" />
                    Pencadangan Database Privat & Terjadwal
                </h3>
                <p class="text-emerald-100 text-sm mt-1 max-w-2xl">
                    Cadangkan data kepegawaian, surat dinas, arsip, dan riwayat legalisir sekolah dengan sekali klik. Berkas SQL disimpan di folder privat server; enkripsi backup akan diselesaikan pada fase V4.5.
                </p>
            </div>
            <x-filament::button color="white" wire:click="createBackup" icon="heroicon-o-plus-circle" class="shadow-lg text-emerald-800 hover:bg-emerald-50">
                Buat Backup Sekarang
            </x-filament::button>
        </div>

        <!-- Daftar Berkas Backup -->
        <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm border border-gray-200 dark:border-gray-700">
            <h4 class="text-base font-bold text-gray-900 dark:text-gray-100 mb-4 flex items-center gap-2">
                <x-filament::icon icon="heroicon-o-folder-arrow-down" class="w-5 h-5 text-emerald-600" />
                Arsip Berkas Cadangan (SQL Snapshots)
            </h4>

            <div class="overflow-x-auto border border-gray-200 dark:border-gray-700 rounded-lg">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Nama Berkas</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Ukuran</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Waktu Dibuat</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-800">
                        @forelse ($this->backup_files as $file)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-750 transition-colors">
                                <td class="px-4 py-3 font-mono text-xs font-semibold text-gray-900 dark:text-gray-100">
                                    {{ $file['name'] }}
                                </td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                                    {{ $file['size'] }}
                                </td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                                    {{ $file['created_at'] }}
                                </td>
                                <td class="px-4 py-3 text-right space-x-2">
                                    <x-filament::button size="xs" color="success" wire:click="downloadBackup('{{ $file['name'] }}')" icon="heroicon-o-arrow-down-tray">
                                        Unduh
                                    </x-filament::button>
                                    <x-filament::button size="xs" color="danger" wire:click="deleteBackup('{{ $file['name'] }}')" icon="heroicon-o-trash" wire:confirm="Yakin ingin menghapus berkas backup ini?">
                                        Hapus
                                    </x-filament::button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="p-6 text-center text-gray-400">
                                    Belum ada berkas backup yang dibuat. Klik tombol di atas untuk membuat cadangan pertama.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Panduan Pemulihan (Restore) -->
        <div class="bg-gray-50 dark:bg-gray-900 rounded-xl p-6 border border-gray-200 dark:border-gray-700 space-y-3">
            <h4 class="text-sm font-bold text-gray-800 dark:text-gray-200 flex items-center gap-2">
                <x-filament::icon icon="heroicon-o-command-line" class="w-5 h-5 text-gray-600" />
                Panduan Prosedur Pemulihan (Database Restore)
            </h4>
            <p class="text-xs text-gray-600 dark:text-gray-400 leading-relaxed">
                Jika sewaktu-waktu terjadi kerusakan server atau kehilangan data, Anda dapat memulihkan seluruh basis data menggunakan terminal/command prompt:
            </p>
            <div class="bg-gray-900 text-emerald-400 p-3 rounded-lg font-mono text-xs overflow-x-auto select-all">
                # Menjalankan pemulihan database via MySQL CLI:<br>
                mysql -u root tata_usaha_db &lt; storage/app/backups/nama_berkas_backup.sql
            </div>
            <p class="text-xs text-gray-500">
                Pencadangan otomatis juga dapat dijadwalkan secara harian via Task Scheduler Windows atau Cron Linux dengan perintah: <code>php artisan app:backup-database</code>.
            </p>
        </div>
    </div>
</x-filament-panels::page>
