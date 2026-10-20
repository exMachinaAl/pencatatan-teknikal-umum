<?php

namespace App\Filament\Widgets;

use App\Models\GajiBerkala;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class KgbJatuhTempoWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = '⏳ Peringatan Jatuh Tempo Kenaikan Gaji Berkala (KGB) - 3 Bulan Ke Depan';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                GajiBerkala::query()
                    ->whereNotNull('tmt_berikutnya')
                    ->where('tmt_berikutnya', '<=', now()->addMonths(3))
                    ->where('tmt_berikutnya', '>=', now()->subMonths(1))
                    ->where('status', '!=', 'diserahkan')
                    ->latest('tmt_berikutnya')
            )
            ->columns([
                Tables\Columns\TextColumn::make('mobile_summary')
                    ->label('Ringkasan')
                    ->state(fn (GajiBerkala $record): string => $record->pegawai?->nama ?: 'Pegawai tidak tersedia')
                    ->description(fn (GajiBerkala $record): string => collect([
                        $record->pegawai?->nip ?: 'Non-NIP',
                        $record->golongan?->kode ? "Gol. {$record->golongan->kode}" : null,
                        $record->tmt_berikutnya ? 'Jatuh tempo '.$record->tmt_berikutnya->translatedFormat('d M Y') : null,
                        GajiBerkala::STATUSES[$record->status] ?? $record->status,
                    ])->filter()->implode(' · '))
                    ->weight('bold')
                    ->wrap()
                    ->extraAttributes(['class' => 'tu-mobile-summary'])
                    ->hiddenFrom('md'),

                Tables\Columns\TextColumn::make('pegawai.nama')
                    ->label('Nama Pegawai')
                    ->weight('bold')
                    ->description(fn (GajiBerkala $record) => $record->pegawai?->nip ?: '-')
                    ->visibleFrom('md'),

                Tables\Columns\TextColumn::make('golongan.kode')
                    ->label('Gol/Ruang')
                    ->badge()
                    ->color('info')
                    ->visibleFrom('md'),

                Tables\Columns\TextColumn::make('tmt')
                    ->label('TMT Terakhir')
                    ->date('d M Y')
                    ->visibleFrom('md'),

                Tables\Columns\TextColumn::make('tmt_berikutnya')
                    ->label('Jatuh Tempo KGB')
                    ->date('d M Y')
                    ->badge()
                    ->color('danger')
                    ->description(fn (GajiBerkala $record) => $record->tmt_berikutnya ? $record->tmt_berikutnya->diffForHumans() : null)
                    ->visibleFrom('md'),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'warning' => 'diproses',
                        'info' => 'sk_terbit',
                    ])
                    ->visibleFrom('md'),

                Tables\Columns\TextColumn::make('nomor_sk')
                    ->label('Nomor SK')
                    ->placeholder('Belum terbit')
                    ->visibleFrom('md'),
            ])
            ->actions([
                Tables\Actions\Action::make('proses')
                    ->label('Buka Berkas')
                    ->icon('heroicon-o-pencil-square')
                    ->url(fn (GajiBerkala $record) => route('filament.admin.resources.gaji-berkalas.edit', $record->id)),
            ])
            ->paginated(false);
    }
}
