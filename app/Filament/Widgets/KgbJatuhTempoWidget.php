<?php

namespace App\Filament\Widgets;

use App\Models\GajiBerkala;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class KgbJatuhTempoWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

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
                Tables\Columns\TextColumn::make('pegawai.nama')
                    ->label('Nama Pegawai')
                    ->weight('bold')
                    ->description(fn (GajiBerkala $record) => $record->pegawai?->nip ?: '-'),

                Tables\Columns\TextColumn::make('golongan.kode')
                    ->label('Gol/Ruang')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('tmt')
                    ->label('TMT Terakhir')
                    ->date('d M Y'),

                Tables\Columns\TextColumn::make('tmt_berikutnya')
                    ->label('Jatuh Tempo KGB')
                    ->date('d M Y')
                    ->badge()
                    ->color('danger')
                    ->description(fn (GajiBerkala $record) => $record->tmt_berikutnya ? $record->tmt_berikutnya->diffForHumans() : null),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'warning' => 'diproses',
                        'info' => 'sk_terbit',
                    ]),

                Tables\Columns\TextColumn::make('nomor_sk')
                    ->label('Nomor SK')
                    ->placeholder('Belum terbit'),
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
