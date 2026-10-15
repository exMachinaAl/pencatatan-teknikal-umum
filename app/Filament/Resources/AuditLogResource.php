<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AuditLogResource\Pages;
use App\Models\AuditLog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Pengaturan & Utilitas';

    protected static ?string $modelLabel = 'Audit Log';

    protected static ?string $pluralModelLabel = 'Audit Log Sistem';

    protected static ?int $navigationSort = 3;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canViewAny(): bool
    {
        return in_array(Auth::user()?->role, ['admin', 'kepala']);
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
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),

                Tables\Columns\TextColumn::make('user_name')
                    ->label('Pengguna')
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\BadgeColumn::make('aksi')
                    ->label('Aksi')
                    ->colors([
                        'success' => 'create',
                        'warning' => 'update',
                        'danger' => 'delete',
                        'info' => 'login',
                        'primary' => 'import',
                    ]),

                Tables\Columns\TextColumn::make('entitas')
                    ->label('Modul')
                    ->searchable(),

                Tables\Columns\TextColumn::make('entitas_id')
                    ->label('ID Record'),

                Tables\Columns\TextColumn::make('ip')
                    ->label('IP Address'),
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
