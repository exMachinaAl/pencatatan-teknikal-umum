<x-filament-panels::page>
    @php
        $roles = \App\Support\TuAccess::roles();
        $permissionRows = $this->permissionRows();
        $auditEntries = $this->recentAudit();
        $roleLabel = \App\Models\User::ROLE_LABELS[auth()->user()?->role] ?? 'Pengguna';
    @endphp

    <div class="tu-access-page">
        <section class="tu-access-hero" aria-labelledby="access-summary-title">
            <div class="tu-access-hero-icon" aria-hidden="true">
                <x-filament::icon icon="heroicon-o-shield-check" />
            </div>
            <div class="tu-access-hero-copy">
                <p class="tu-access-eyebrow">Kontrol akses sistem</p>
                <h2 id="access-summary-title">Akses setiap peran dalam satu tampilan</h2>
                <p>Lihat siapa yang dapat membuka, membuat, mengubah, atau mengelola setiap modul.</p>
            </div>
            <div class="tu-access-current-role">
                <span>Peran Anda</span>
                <strong>{{ $roleLabel }}</strong>
            </div>
        </section>

        <div class="tu-access-stats" aria-label="Ringkasan hak akses">
            <div class="tu-access-stat">
                <span>Peran sistem</span>
                <strong>{{ count($roles) }}</strong>
            </div>
            <div class="tu-access-stat">
                <span>Modul terkontrol</span>
                <strong>{{ count($permissionRows) }}</strong>
            </div>
            <div class="tu-access-stat">
                <span>Aktivitas terbaru</span>
                <strong>{{ count($auditEntries) }}</strong>
            </div>
        </div>

        <section class="tu-access-panel" aria-labelledby="permission-matrix-title">
            <header class="tu-access-panel-header">
                <div>
                    <p class="tu-access-eyebrow">Matriks peran</p>
                    <h2 id="permission-matrix-title">Hak akses per modul</h2>
                </div>
                <p>Hak akses aktif yang diterapkan oleh sistem.</p>
            </header>

            <div class="tu-access-table-wrap">
                <table class="tu-access-table">
                    <thead>
                        <tr>
                            <th scope="col">Modul</th>
                            @foreach ($roles as $label)
                                <th scope="col">{{ $label }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($permissionRows as $entry)
                            <tr>
                                <th scope="row">{{ $entry['module'] }}</th>
                                @foreach (array_keys($roles) as $role)
                                    @php($access = $entry['access'][$role])
                                    <td>
                                        @if ($access === 'Tidak ada')
                                            <span class="tu-access-empty">Tidak ada</span>
                                        @else
                                            <div class="tu-access-badges">
                                                @foreach (explode(', ', $access) as $ability)
                                                    <span class="tu-access-badge">{{ $ability }}</span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="tu-access-mobile-list">
                @foreach ($permissionRows as $entry)
                    <article class="tu-access-module-card">
                        <h3>{{ $entry['module'] }}</h3>
                        <dl>
                            @foreach ($roles as $role => $label)
                                @php($access = $entry['access'][$role])
                                <div>
                                    <dt>{{ $label }}</dt>
                                    <dd>
                                        @if ($access === 'Tidak ada')
                                            <span class="tu-access-empty">Tidak ada</span>
                                        @else
                                            <div class="tu-access-badges">
                                                @foreach (explode(', ', $access) as $ability)
                                                    <span class="tu-access-badge">{{ $ability }}</span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </dd>
                                </div>
                            @endforeach
                        </dl>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="tu-access-panel" aria-labelledby="recent-audit-title">
            <header class="tu-access-panel-header tu-access-panel-header--action">
                <div>
                    <p class="tu-access-eyebrow">Pemantauan</p>
                    <h2 id="recent-audit-title">Aktivitas perubahan terbaru</h2>
                </div>
                <a class="tu-access-link" href="{{ \App\Filament\Resources\AuditLogResource::getUrl() }}">
                    Lihat semua audit
                    <x-filament::icon icon="heroicon-m-arrow-right" />
                </a>
            </header>

            <div class="tu-audit-list">
                @forelse ($auditEntries as $entry)
                    <article class="tu-audit-row">
                        <div class="tu-audit-icon" aria-hidden="true">
                            <x-filament::icon icon="heroicon-o-clock" />
                        </div>
                        <div class="tu-audit-copy">
                            <strong>{{ $entry['user'] }}</strong>
                            <span>{{ \Illuminate\Support\Str::headline($entry['action']) }} · {{ \Illuminate\Support\Str::headline($entry['entity']) }}</span>
                        </div>
                        <time datetime="{{ $entry['iso'] }}">{{ $entry['when'] }}</time>
                    </article>
                @empty
                    <div class="tu-access-empty-state">
                        <x-filament::icon icon="heroicon-o-clipboard-document-list" />
                        <strong>Belum ada aktivitas</strong>
                        <span>Perubahan data akan muncul di bagian ini.</span>
                    </div>
                @endforelse
            </div>
        </section>
    </div>
</x-filament-panels::page>
