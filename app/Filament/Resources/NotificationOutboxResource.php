<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\AuthorizesModuleAccess;
use App\Filament\Resources\NotificationOutboxResource\Pages;
use App\Models\NotificationOutbox;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class NotificationOutboxResource extends Resource
{
    use AuthorizesModuleAccess;

    protected static ?string $model = NotificationOutbox::class;

    protected static ?string $navigationIcon = 'heroicon-o-bell-alert';

    protected static ?string $navigationLabel = 'Antrean Notifikasi';

    protected static ?string $navigationGroup = 'Layanan Publik';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Notifikasi';

    protected static ?string $pluralModelLabel = 'Antrean Notifikasi';

    protected static function accessModule(): string
    {
        return 'legalisir';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('mobile_summary')
                    ->label('Ringkasan')
                    ->state(fn (NotificationOutbox $record): string => data_get($record->payload, 'nomor_antrean') ?: "Notifikasi #{$record->id}")
                    ->description(fn (NotificationOutbox $record): string => collect([
                        strtoupper($record->kanal),
                        $record->tujuan,
                        str($record->status)->replace('_', ' ')->title(),
                        $record->created_at?->translatedFormat('d M Y, H:i'),
                    ])->filter()->implode(' · '))
                    ->weight('bold')
                    ->wrap()
                    ->extraAttributes(['class' => 'tu-mobile-summary'])
                    ->hiddenFrom('md'),

                Tables\Columns\TextColumn::make('id')->label('No.')->rowIndex()->visibleFrom('md'),
                Tables\Columns\TextColumn::make('kanal')->label('Kanal')->badge()->formatStateUsing(fn (string $state) => strtoupper($state))->visibleFrom('md'),
                Tables\Columns\TextColumn::make('tujuan')->label('Tujuan')->searchable()->limit(32)->visibleFrom('md'),
                Tables\Columns\TextColumn::make('payload.nomor_antrean')->label('No. Antrean')->searchable()->visibleFrom('md'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')->badge()
                    ->color(fn (string $state) => match ($state) {
                        'terkirim' => 'success',
                        'gagal' => 'danger',
                        'menunggu_provider' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => str($state)->replace('_', ' ')->title())
                    ->visibleFrom('md'),
                Tables\Columns\TextColumn::make('created_at')->label('Dibuat')->dateTime('d M Y, H:i')->sortable()->visibleFrom('md'),
                Tables\Columns\TextColumn::make('error_terakhir')->label('Keterangan')->limit(45)->toggleable()->visibleFrom('md'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('kanal')->options(['email' => 'Email', 'whatsapp' => 'WhatsApp']),
                Tables\Filters\SelectFilter::make('status')->options([
                    'antre' => 'Antre', 'terkirim' => 'Terkirim', 'gagal' => 'Gagal',
                    'menunggu_provider' => 'Menunggu Provider',
                ]),
            ])
            ->actions([
                Tables\Actions\Action::make('antrekan_ulang')
                    ->label('Antrekan ulang')
                    ->icon('heroicon-o-arrow-path')
                    ->authorize(fn (): bool => static::resourcePermission('transition'))
                    ->visible(fn (NotificationOutbox $record): bool => $record->kanal === 'email' && $record->status === 'gagal')
                    ->requiresConfirmation()
                    ->action(function (NotificationOutbox $record): void {
                        static::authorizeResourceAction('transition');
                        $record->update(['status' => 'antre', 'error_terakhir' => null]);
                    }),
            ])
            ->paginated([10, 25, 50]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNotificationOutboxes::route('/'),
        ];
    }
}
