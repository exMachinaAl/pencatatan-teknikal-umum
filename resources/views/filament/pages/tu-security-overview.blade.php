<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">Hak akses CRUD standar per modul</x-slot>
            <x-slot name="description">Ringkasan kebijakan Laravel Policy. Tombol aksi kustom pada Resource lama harus diperiksa secara terpisah; ini bukan klaim bahwa seluruh endpoint sudah terlindungi.</x-slot>
            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                <table class="w-full min-w-[740px] text-sm text-left">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="p-3">Modul</th>
                            @foreach (\App\Support\TuAccess::roles() as $label)
                                <th class="p-3 whitespace-nowrap">{{ $label }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach ($this->permissionRows() as $entry)
                            <tr>
                                <td class="p-3 font-medium">{{ $entry['module'] }}</td>
                                @foreach (array_keys(\App\Support\TuAccess::roles()) as $role)
                                    <td class="p-3">{{ $entry['access'][$role] }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">12 aktivitas perubahan terakhir</x-slot>
            <x-slot name="description">Diambil dari audit_log yang sudah ada. Tidak menampilkan nilai sensitif sebelum/sesudah perubahan.</x-slot>
            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                <table class="w-full min-w-[580px] text-sm text-left">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr><th class="p-3">Waktu</th><th class="p-3">Pengguna</th><th class="p-3">Aksi</th><th class="p-3">Modul</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse ($this->recentAudit() as $entry)
                            <tr>
                                <td class="p-3 whitespace-nowrap">{{ $entry['when'] }}</td>
                                <td class="p-3">{{ $entry['user'] }}</td>
                                <td class="p-3">{{ $entry['action'] }}</td>
                                <td class="p-3">{{ $entry['entity'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="p-4 text-center">Belum ada aktivitas yang tercatat.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <p class="mt-3 text-xs text-gray-600 dark:text-gray-400">Sumber: observer create/update/delete/restore bawaan proyek. Audit percobaan akses, login, dan unduhan export belum ditambahkan pada rilis ini.</p>
        </x-filament::section>
    </div>
</x-filament-panels::page>
