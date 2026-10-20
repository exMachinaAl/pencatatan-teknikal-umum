<?php

namespace App\Filament\Widgets;

use App\Models\SuratMasuk;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class SuratMasukTerbaruWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = '📥 Surat Masuk Terbaru Butuh Disposisi / Tindak Lanjut';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                SuratMasuk::query()
                    ->whereIn('status', ['baru', 'didisposisi'])
                    ->latest('tanggal_diterima')
                    ->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('mobile_summary')
                    ->label('Ringkasan')
                    ->state(fn (SuratMasuk $record): string => $record->perihal)
                    ->description(fn (SuratMasuk $record): string => collect([
                        $record->nomor_agenda,
                        $record->pengirim,
                        $record->tanggal_diterima?->translatedFormat('d M Y'),
                        SuratMasuk::STATUSES[$record->status] ?? $record->status,
                    ])->filter()->implode(' · '))
                    ->weight('bold')
                    ->wrap()
                    ->extraAttributes(['class' => 'tu-mobile-summary'])
                    ->hiddenFrom('md'),

                Tables\Columns\TextColumn::make('nomor_agenda')
                    ->label('No. Agenda')
                    ->weight('bold')
                    ->visibleFrom('md'),

                Tables\Columns\TextColumn::make('tanggal_diterima')
                    ->label('Diterima')
                    ->date('d M Y')
                    ->visibleFrom('md'),

                Tables\Columns\TextColumn::make('pengirim')
                    ->label('Instansi Pengirim')
                    ->weight('semibold')
                    ->limit(25)
                    ->visibleFrom('md'),

                Tables\Columns\TextColumn::make('perihal')
                    ->label('Perihal')
                    ->limit(45)
                    ->visibleFrom('md'),

                Tables\Columns\BadgeColumn::make('sifat')
                    ->label('Sifat')
                    ->colors([
                        'gray' => 'biasa',
                        'warning' => 'penting',
                        'danger' => 'segera',
                        'primary' => 'rahasia',
                    ])
                    ->visibleFrom('md'),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'info' => 'baru',
                        'warning' => 'didisposisi',
                    ])
                    ->visibleFrom('md'),
            ])
            ->actions([
                Tables\Actions\Action::make('disposisi')
                    ->label('Disposisi / Detail')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->url(fn (SuratMasuk $record) => route('filament.admin.resources.surat-masuks.edit', $record->id)),
            ])
            ->paginated(false);
    }
}
