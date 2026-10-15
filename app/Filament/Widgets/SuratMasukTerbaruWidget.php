<?php

namespace App\Filament\Widgets;

use App\Models\SuratMasuk;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class SuratMasukTerbaruWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 'full';

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
                Tables\Columns\TextColumn::make('nomor_agenda')
                    ->label('No. Agenda')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('tanggal_diterima')
                    ->label('Diterima')
                    ->date('d M Y'),

                Tables\Columns\TextColumn::make('pengirim')
                    ->label('Instansi Pengirim')
                    ->weight('semibold')
                    ->limit(25),

                Tables\Columns\TextColumn::make('perihal')
                    ->label('Perihal')
                    ->limit(45),

                Tables\Columns\BadgeColumn::make('sifat')
                    ->label('Sifat')
                    ->colors([
                        'gray' => 'biasa',
                        'warning' => 'penting',
                        'danger' => 'segera',
                        'primary' => 'rahasia',
                    ]),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'info' => 'baru',
                        'warning' => 'didisposisi',
                    ]),
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
