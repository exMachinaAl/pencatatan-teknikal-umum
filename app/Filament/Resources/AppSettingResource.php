<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AppSettingResource\Pages;
use App\Models\AppSetting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class AppSettingResource extends Resource
{
    protected static ?string $model = AppSetting::class;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'Pengaturan & Utilitas';

    protected static ?string $modelLabel = 'Konfigurasi Sistem';

    protected static ?string $pluralModelLabel = 'Pengaturan Aplikasi';

    protected static ?int $navigationSort = 5;

    public static function canViewAny(): bool
    {
        return in_array(Auth::user()?->role, ['admin', 'kepala']);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('key')
                    ->label('Kunci Pengaturan (Key)')
                    ->required()
                    ->disabled(fn ($record) => $record !== null),

                Forms\Components\TextInput::make('deskripsi')
                    ->label('Keterangan / Fungsi Pengaturan')
                    ->maxLength(255),

                Forms\Components\Textarea::make('value')
                    ->label('Nilai Pengaturan (Value)')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('key')
                    ->label('Kunci')
                    ->weight('bold')
                    ->searchable(),

                Tables\Columns\TextColumn::make('value')
                    ->label('Nilai Pengaturan')
                    ->limit(50),

                Tables\Columns\TextColumn::make('deskripsi')
                    ->label('Keterangan')
                    ->limit(60),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Terakhir Diubah')
                    ->dateTime('d M Y H:i'),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
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
            'index' => Pages\ListAppSettings::route('/'),
            'create' => Pages\CreateAppSetting::route('/create'),
            'edit' => Pages\EditAppSetting::route('/{record}/edit'),
        ];
    }
}
