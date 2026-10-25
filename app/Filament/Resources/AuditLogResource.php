<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AuditLogResource\Pages;
use App\Filament\Resources\Concerns\AuthorizesModuleAccess;
use App\Models\AuditLog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AuditLogResource extends Resource
{
    use AuthorizesModuleAccess;

    protected static ?string $model = AuditLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Pengaturan & Utilitas';

    protected static ?string $modelLabel = 'Audit Log';

    protected static ?string $pluralModelLabel = 'Audit Log Sistem';

    protected static ?int $navigationSort = 3;

    protected static function accessModule(): string
    {
        return 'audit';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('user_name')
                    ->label('Nama Pengguna')
                    ->disabled(),

                Forms\Components\TextInput::make('aksi')
                    ->label('Aksi')
                    ->disabled(),

                Forms\Components\TextInput::make('entitas')
                    ->label('Entitas Modul')
                    ->disabled(),

                Forms\Components\TextInput::make('entitas_id')
                    ->label('ID Data')
                    ->disabled(),

                Forms\Components\TextInput::make('ip')
                    ->label('Alamat IP')
                    ->disabled(),

                Forms\Components\TextInput::make('created_at')
                    ->label('Waktu Kejadian')
                    ->disabled(),

                Forms\Components\KeyValue::make('data_sebelum')
                    ->label('Data Sebelum Diubah')
                    ->disabled()
                    ->columnSpanFull(),

                Forms\Components\KeyValue::make('data_sesudah')
                    ->label('Data Setelah Diubah')
                    ->disabled()
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('mobile_summary')
                    ->label('Ringkasan')
                    ->state(fn (AuditLog $record): string => strtoupper($record->aksi).' · '.$record->entitas)
                    ->description(fn (AuditLog $record): string => collect([
                        $record->user_name ?: 'Sistem',
                        $record->created_at?->format('d/m/Y H:i'),
                        $record->entitas_id ? 'ID '.$record->entitas_id : null,
                        $record->ip,
                    ])->filter()->implode(' · '))
                    ->weight('bold')
                    ->wrap()
                    ->extraAttributes(['class' => 'tu-mobile-summary'])
                    ->hiddenFrom('md'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable()
                    ->visibleFrom('md'),

                Tables\Columns\TextColumn::make('user_name')
                    ->label('Pengguna')
                    ->searchable()
                    ->description(fn (AuditLog $record): string => collect([
                        $record->user_username ? '@'.$record->user_username : null,
                        $record->user_role,
                    ])->filter()->implode(' · '))
                    ->weight('bold')
                    ->visibleFrom('md'),

                Tables\Columns\BadgeColumn::make('aksi')
                    ->label('Aksi')
                    ->colors([
                        'success' => 'create',
                        'warning' => 'update',
                        'danger' => 'delete',
                        'info' => 'login',
                        'primary' => 'import',
                    ])
                    ->visibleFrom('md'),

                Tables\Columns\TextColumn::make('entitas')
                    ->label('Modul')
                    ->searchable()
                    ->visibleFrom('md'),

                Tables\Columns\TextColumn::make('entitas_id')
                    ->label('ID Record')
                    ->visibleFrom('md'),

                Tables\Columns\TextColumn::make('ip')
                    ->label('IP Address')
                    ->visibleFrom('md'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('aksi')
                    ->options([
                        'create' => 'Create (Buat)',
                        'update' => 'Update (Ubah)',
                        'delete' => 'Delete (Hapus)',
                        'login' => 'Login',
                        'export' => 'Export',
                        'import' => 'Import',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAuditLogs::route('/'),
        ];
    }
}
