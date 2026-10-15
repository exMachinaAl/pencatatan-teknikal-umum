<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'Pengaturan & Utilitas';

    protected static ?string $modelLabel = 'Pengguna Sistem';

    protected static ?string $pluralModelLabel = 'Kelola Pengguna';

    protected static ?int $navigationSort = 4;

    public static function canViewAny(): bool
    {
        // Hanya admin yang bisa mengelola akun pengguna
        return Auth::user()?->isAdmin() ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Akun & Hak Akses')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nama Lengkap')
                            ->required()
                            ->maxLength(120),

                        Forms\Components\TextInput::make('email')
                            ->label('Alamat Email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(160),

                        Forms\Components\TextInput::make('password')
                            ->label('Kata Sandi')
                            ->password()
                            ->dehydrateStateUsing(fn ($state) => filled($state) ? Hash::make($state) : null)
                            ->dehydrated(fn ($state) => filled($state))
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->helperText('Kosongkan jika tidak ingin mengubah kata sandi'),

                        Forms\Components\Select::make('role')
                            ->label('Peran / Hak Akses')
                            ->options([
                                'admin' => 'Admin (Operator TU - Akses Penuh)',
                                'kepala' => 'Kepala TU / Kepala Sekolah (Verifikasi & Lihat Gaji)',
                                'staf' => 'Staf TU (Operasional Harian)',
                                'pegawai' => 'Pegawai / Guru (Portal Mandiri)',
                            ])
                            ->default('staf')
                            ->required(),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Status Akun Aktif')
                            ->default(true),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Pengguna')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),

                Tables\Columns\BadgeColumn::make('role')
                    ->label('Peran')
                    ->colors([
                        'danger' => 'admin',
                        'primary' => 'kepala',
                        'warning' => 'staf',
                        'gray' => 'pegawai',
                    ])
                    ->formatStateUsing(function (string $state): string {
                        return match ($state) {
                            'admin' => 'Admin TU',
                            'kepala' => 'Kepala Sekolah / TU',
                            'staf' => 'Staf TU',
                            'pegawai' => 'Pegawai',
                            default => $state,
                        };
                    })
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->options([
                        'admin' => 'Admin',
                        'kepala' => 'Kepala',
                        'staf' => 'Staf',
                        'pegawai' => 'Pegawai',
                    ]),
                Tables\Filters\TernaryFilter::make('is_active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
