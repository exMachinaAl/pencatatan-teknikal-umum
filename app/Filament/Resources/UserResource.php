<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\AuthorizesModuleAccess;
use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

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

                        Forms\Components\TextInput::make('email')
                            ->label('Alamat Email')
                            ->email()
                            ->rule('not_regex:/[\r\n]/')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(160),

                        Forms\Components\TextInput::make('nomor_telepon')
                            ->label('Nomor Telepon / WhatsApp')
                            ->tel()
                            ->rule('regex:/^[0-9+().\-\s]{6,30}$/')
                            ->maxLength(30),

                        Forms\Components\TextInput::make('jabatan')
                            ->label('Jabatan Organisasi')
                            ->placeholder('Contoh: Kepala TU, Administrator Infrastruktur')
                            ->maxLength(120),

                        Forms\Components\TextInput::make('unit_kerja')
                            ->label('Unit / Divisi')
                            ->placeholder('Contoh: Tata Usaha, Divisi IT')
                            ->maxLength(120),

                        Forms\Components\Select::make('pegawai_id')
                            ->label('Terhubung ke Data Pegawai')
                            ->relationship('pegawai', 'nama')
                            ->searchable()
                            ->preload()
                            ->unique(ignoreRecord: true)
                            ->helperText('Opsional. Hubungkan akun dengan satu data pegawai yang sama.'),
                    ])->columns(2),

                Forms\Components\Section::make('Foto profil')
                    ->description('Foto ditampilkan sebagai avatar dan tetap disimpan di penyimpanan privat.')
                    ->schema([
                        Forms\Components\FileUpload::make('foto_path')
                            ->label('Foto Pengurus / Pengguna')
                            ->disk('local')
                            ->directory('profil_pengguna')
                            ->visibility('private')
                            ->image()
                            ->imageEditor()
                            ->imagePreviewHeight('180')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->maxSize(2048)
                            ->getUploadedFileUsing(function (?User $record, string $file): ?array {
                                $storage = Storage::disk('local');

                                if (! $record || ! $storage->exists($file)) {
                                    return null;
                                }

                                return [
                                    'name' => basename($file),
                                    'size' => $storage->size($file),
                                    'type' => $storage->mimeType($file),
                                    'url' => route('profil.pengguna.foto', $record),
                                ];
                            }),

                        Forms\Components\Placeholder::make('foto_saat_ini')
                            ->label('Preview tersimpan')
                            ->content(fn (?User $record): HtmlString|string => $record?->foto_path
                                ? new HtmlString('<img src="'.e(route('profil.pengguna.foto', $record)).'" alt="Foto profil" style="width:8rem;height:8rem;object-fit:cover;border-radius:9999px;border:1px solid #64748b">')
                                : 'Belum ada foto profil.')
                            ->visible(fn (?User $record): bool => $record !== null),
                    ])->columns(2)->collapsible(),

                Forms\Components\Section::make('Peran dan status')
                    ->description('Akses modul ditentukan oleh peran. Pengguna tidak dapat mengubah perannya sendiri.')
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
                            ->minLength(8)
                            ->dehydrateStateUsing(fn ($state) => filled($state) ? Hash::make($state) : null)
                            ->dehydrated(fn ($state) => filled($state))
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->helperText(fn (string $operation): string => $operation === 'edit'
                                ? 'Biarkan kosong jika kata sandi tidak diubah.'
                                : 'Minimal 8 karakter.'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('foto_path')
                    ->label('Foto')
                    ->getStateUsing(fn (User $record): ?string => $record->foto_path ? route('profil.pengguna.foto', $record) : null)
                    ->circular()
                    ->height(40),

                Tables\Columns\TextColumn::make('nomor')
                    ->label('No.')
                    ->rowIndex(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Pengguna')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (User $record): string => $record->email),

                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->visibleFrom('md'),

                Tables\Columns\TextColumn::make('jabatan')
                    ->label('Jabatan / Unit')
                    ->formatStateUsing(fn (?string $state): string => $state ?: '-')
                    ->description(fn (User $record): ?string => $record->unit_kerja)
                    ->searchable(['jabatan', 'unit_kerja', 'nomor_telepon'])
                    ->toggleable(),

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
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Status')
                    ->boolean(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
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
