<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\AuthorizesModuleAccess;
use App\Filament\Resources\UserResource\Pages;
use App\Models\Pegawai;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    use AuthorizesModuleAccess;

    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'Pengaturan & Utilitas';

    protected static ?string $modelLabel = 'Pengguna Sistem';

    protected static ?string $pluralModelLabel = 'Kelola Pengguna';

    protected static ?int $navigationSort = 4;

    protected static function accessModule(): string
    {
        return 'users';
    }

    public static function canDelete(Model $record): bool
    {
        return static::resourcePermission('delete')
            && auth()->id() !== $record->getKey();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Identitas akun')
                    ->description('Informasi yang digunakan untuk mengenali dan masuk ke sistem.')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nama Lengkap')
                            ->required()
                            ->maxLength(120),

                        Forms\Components\TextInput::make('username')
                            ->label('Username')
                            ->required()
                            ->minLength(3)
                            ->maxLength(50)
                            ->regex('/^[a-zA-Z0-9][a-zA-Z0-9._-]{2,49}$/')
                            ->unique(ignoreRecord: true)
                            ->helperText('Dipakai untuk login. Contoh: admin, kepala_tu, atau staf.tu.'),

                        Forms\Components\TextInput::make('email')
                            ->label('Email (opsional)')
                            ->email()
                            ->rule('not_regex:/[\r\n]/')
                            ->unique(ignoreRecord: true)
                            ->maxLength(160)
                            ->helperText('Tetap dapat dipakai untuk login jika diisi.'),

                        Forms\Components\Select::make('pegawai_id')
                            ->label('NIP pegawai (opsional)')
                            ->relationship('pegawai', 'nama')
                            ->getOptionLabelFromRecordUsing(fn (Pegawai $record): string => ($record->nip ? $record->nip.' — ' : '').$record->nama)
                            ->searchable(['nip', 'nama'])
                            ->preload()
                            ->unique(ignoreRecord: true)
                            ->helperText('Jika dipilih, NIP pegawai juga dapat dipakai untuk login.'),
                    ])->columns(2),

                Forms\Components\Section::make('Peran dan status')
                    ->description('Bagian sensitif: akses ditentukan oleh peran dan perubahan akun dicatat. Pengguna tidak dapat mengubah perannya sendiri.')
                    ->schema([
                        Forms\Components\Select::make('role')
                            ->label('Peran')
                            ->options(User::ROLE_LABELS)
                            ->default(User::ROLE_PETUGAS_TU)
                            ->disabled(fn (?User $record): bool => $record?->is(auth()->user()) ?? false)
                            ->helperText('Administrator Sistem adalah fasilitator teknis Divisi IT di luar hierarki TU; Kepala TU adalah pimpinan operasional tertinggi di TU.')
                            ->required(),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Akun aktif')
                            ->disabled(fn (?User $record): bool => $record?->is(auth()->user()) ?? false)
                            ->helperText('Akun nonaktif tidak dapat masuk ke panel.')
                            ->default(true),
                    ])->columns(2),

                Forms\Components\Section::make('Kata sandi')
                    ->description('Gunakan kata sandi yang unik untuk akun ini.')
                    ->collapsed(fn (string $operation): bool => $operation === 'edit')
                    ->collapsible(fn (string $operation): bool => $operation === 'edit')
                    ->schema([
                        Forms\Components\TextInput::make('password')
                            ->label('Kata sandi baru')
                            ->password()
                            ->revealable()
                            ->minLength(12)
                            ->dehydrateStateUsing(fn ($state) => filled($state) ? Hash::make($state) : null)
                            ->dehydrated(fn ($state) => filled($state))
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->helperText(fn (string $operation): string => $operation === 'edit'
                                ? 'Biarkan kosong jika kata sandi tidak diubah.'
                                : 'Minimal 12 karakter dan gunakan password berbeda untuk setiap akun.'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('mobile_summary')
                    ->label('Ringkasan')
                    ->state(fn (User $record): string => $record->name)
                    ->description(fn (User $record): string => collect([
                        '@'.($record->username ?: '-'),
                        $record->pegawai?->nip ? 'NIP '.$record->pegawai->nip : null,
                        User::ROLE_LABELS[$record->role] ?? $record->role,
                        $record->is_active ? 'Aktif' : 'Nonaktif',
                    ])->filter()->implode(' · '))
                    ->weight('bold')
                    ->wrap()
                    ->extraAttributes(['class' => 'tu-mobile-summary'])
                    ->hiddenFrom('md'),

                Tables\Columns\TextColumn::make('nomor')
                    ->label('No.')
                    ->rowIndex()
                    ->visibleFrom('md'),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Pengguna')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (User $record): string => '@'.($record->username ?: '-').($record->pegawai?->nip ? ' · NIP '.$record->pegawai->nip : ''))
                    ->visibleFrom('md'),

                Tables\Columns\TextColumn::make('username')
                    ->label('Username')
                    ->searchable()
                    ->visibleFrom('md'),

                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->visibleFrom('md'),

                Tables\Columns\BadgeColumn::make('role')
                    ->label('Peran')
                    ->color(fn (string $state): string => match ($state) {
                        User::ROLE_ADMIN => 'danger',
                        User::ROLE_KEPALA_TU => 'primary',
                        User::ROLE_PETUGAS_TU => 'info',
                        User::ROLE_KEPEGAWAIAN => 'warning',
                        User::ROLE_KEPALA_SEKOLAH => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => User::ROLE_LABELS[$state] ?? $state)
                    ->sortable()
                    ->visibleFrom('md'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Status')
                    ->boolean()
                    ->visibleFrom('md'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable()
                    ->visibleFrom('md'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->options(User::ROLE_LABELS),
                Tables\Filters\TernaryFilter::make('is_active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->hidden(fn (User $record): bool => auth()->id() === $record->getKey()),
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
