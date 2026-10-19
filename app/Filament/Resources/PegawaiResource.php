<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\AuthorizesModuleAccess;
use App\Filament\Resources\PegawaiResource\Pages;
use App\Models\Pegawai;
use App\Models\RefGolongan;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class PegawaiResource extends Resource
{
    use AuthorizesModuleAccess;

    protected static ?string $model = Pegawai::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Administrasi Kepegawaian';

    protected static ?string $modelLabel = 'Pegawai';

    protected static ?string $pluralModelLabel = 'Data Pegawai';

    protected static ?int $navigationSort = 1;

    protected static function accessModule(): string
    {
        return 'kepegawaian';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Identitas Pegawai')
                    ->description('Informasi data pribadi dan kepegawaian')
                    ->schema([
                        Forms\Components\TextInput::make('nama')
                            ->label('Nama Lengkap & Gelar')
                            ->required()
                            ->maxLength(150),

                        Forms\Components\TextInput::make('nip')
                            ->label('NIP / Nomor Induk')
                            ->helperText('Kosongkan jika pegawai Non-PNS / Honorer')
                            ->maxLength(30)
                            ->unique(ignoreRecord: true),

                        Forms\Components\FileUpload::make('foto_path')
                            ->label('Foto Profil Pegawai')
                            ->disk('local')
                            ->directory('profil_pegawai')
                            ->visibility('private')
                            ->image()
                            ->imageEditor()
                            ->imagePreviewHeight('180')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->maxSize(2048)
                            ->getUploadedFileUsing(function (?Pegawai $record, string $file): ?array {
                                $storage = Storage::disk('local');

                                if (! $record || ! $storage->exists($file)) {
                                    return null;
                                }

                                return [
                                    'name' => basename($file),
                                    'size' => $storage->size($file),
                                    'type' => $storage->mimeType($file),
                                    'url' => route('profil.pegawai.foto', $record),
                                ];
                            }),

                        Forms\Components\Placeholder::make('foto_saat_ini')
                            ->label('Preview tersimpan')
                            ->content(fn (?Pegawai $record): HtmlString|string => $record?->foto_path
                                ? new HtmlString('<img src="'.e(route('profil.pegawai.foto', $record)).'" alt="Foto pegawai" style="width:8rem;height:8rem;object-fit:cover;border-radius:9999px;border:1px solid #64748b">')
                                : 'Belum ada foto profil.')
                            ->visible(fn (?Pegawai $record): bool => $record !== null),

                        Forms\Components\Select::make('status_kepegawaian')
                            ->label('Status Kepegawaian')
                            ->options([
                                'PNS' => 'PNS (Pegawai Negeri Sipil)',
                                'PPPK' => 'PPPK (Pegawai Pemerintah dengan Perjanjian Kerja)',
                                'Honorer' => 'Honorer / Tenaga Kerja Sukarela',
                            ])
                            ->default('PNS')
                            ->required(),

                        Forms\Components\TextInput::make('jabatan')
                            ->label('Jabatan / Tugas Mengajar')
                            ->placeholder('Contoh: Guru Matematika Ahli Madya, Staf TU')
                            ->maxLength(120),

                        Forms\Components\Select::make('golongan_id')
                            ->label('Golongan / Ruang')
                            ->relationship('golongan', 'kode')
                            ->getOptionLabelFromRecordUsing(fn (RefGolongan $record) => "{$record->pangkat} ({$record->kode})")
                            ->searchable()
                            ->preload(),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Status Aktif')
                            ->default(true)
                            ->inline(false),
                    ])->columns(2),

                Forms\Components\Section::make('Kontak & Biodata')
                    ->description('Informasi pendukung untuk komunikasi dan administrasi internal.')
                    ->schema([
                        Forms\Components\TextInput::make('email')
                            ->label('Email Pegawai')
                            ->email()
                            ->rule('not_regex:/[\r\n]/')
                            ->unique(ignoreRecord: true)
                            ->maxLength(160),

                        Forms\Components\TextInput::make('nomor_telepon')
                            ->label('Nomor Telepon / WhatsApp')
                            ->tel()
                            ->rule('regex:/^[0-9+().\-\s]{6,30}$/')
                            ->maxLength(30),

                        Forms\Components\TextInput::make('tempat_lahir')
                            ->label('Tempat Lahir')
                            ->maxLength(100),

                        Forms\Components\DatePicker::make('tanggal_lahir')
                            ->label('Tanggal Lahir')
                            ->maxDate(now())
                            ->displayFormat('d/m/Y'),

                        Forms\Components\Select::make('jenis_kelamin')
                            ->label('Jenis Kelamin')
                            ->options(Pegawai::JENIS_KELAMIN),

                        Forms\Components\Textarea::make('alamat')
                            ->label('Alamat Domisili')
                            ->rows(3)
                            ->maxLength(1000)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('unit_kerja')
                            ->label('Unit Kerja')
                            ->placeholder('Contoh: Tata Usaha, Kurikulum, Laboratorium')
                            ->maxLength(120),

                        Forms\Components\TextInput::make('pendidikan_terakhir')
                            ->label('Pendidikan Terakhir')
                            ->placeholder('Contoh: S1 Administrasi Pendidikan')
                            ->maxLength(100),

                        Forms\Components\DatePicker::make('tanggal_mulai_tugas')
                            ->label('Tanggal Mulai Bertugas')
                            ->displayFormat('d/m/Y'),
                    ])->columns(2)->collapsible(),

                Forms\Components\Section::make('Riwayat TMT Terakhir')
                    ->description('Digunakan untuk kalkulasi otomatis Kenaikan Pangkat (4 thn) & KGB (2 thn)')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Forms\Components\DatePicker::make('tmt_pangkat_terakhir')
                            ->label('TMT Pangkat Terakhir')
                            ->helperText('TMT SK Kenaikan Pangkat terakhir')
                            ->displayFormat('d/m/Y'),

                        Forms\Components\DatePicker::make('tmt_kgb_terakhir')
                            ->label('TMT KGB Terakhir')
                            ->helperText('TMT Kenaikan Gaji Berkala terakhir')
                            ->displayFormat('d/m/Y'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('foto_path')
                    ->label('Foto')
                    ->getStateUsing(fn (Pegawai $record): ?string => $record->foto_path ? route('profil.pegawai.foto', $record) : null)
                    ->circular()
                    ->height(40),

                Tables\Columns\TextColumn::make('nama')
                    ->label('Nama Pegawai')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (Pegawai $record) => $record->nip ?: 'Non-NIP'),

                Tables\Columns\BadgeColumn::make('status_kepegawaian')
                    ->label('Status')
                    ->colors([
                        'primary' => 'PNS',
                        'warning' => 'PPPK',
                        'gray' => 'Honorer',
                    ])
                    ->sortable(),

                Tables\Columns\TextColumn::make('jabatan')
                    ->label('Jabatan')
                    ->searchable()
                    ->limit(30)
                    ->description(fn (Pegawai $record): ?string => $record->unit_kerja),

                Tables\Columns\TextColumn::make('nomor_telepon')
                    ->label('Kontak')
                    ->searchable(['nomor_telepon', 'email'])
                    ->description(fn (Pegawai $record): ?string => $record->email)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('golongan.kode')
                    ->label('Gol/Ruang')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                Tables\Columns\TextColumn::make('tmt_pangkat_terakhir')
                    ->label('TMT Pangkat')
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('perkiraan_kp_berikutnya')
                    ->label('Estimasi KP')
                    ->state(function (Pegawai $record) {
                        return $record->perkiraan_kp_berikutnya?->translatedFormat('M Y');
                    })
                    ->badge()
                    ->color(fn (Pegawai $record) => $record->is_calon_kp ? 'danger' : 'gray')
                    ->description(fn (Pegawai $record) => $record->is_calon_kp ? '⚡ Calon KP' : null),

                Tables\Columns\TextColumn::make('perkiraan_kgb_berikutnya')
                    ->label('Estimasi KGB')
                    ->state(function (Pegawai $record) {
                        return $record->perkiraan_kgb_berikutnya?->translatedFormat('d M Y');
                    })
                    ->badge()
                    ->color(fn (Pegawai $record) => $record->is_jatuh_tempo_kgb ? 'warning' : 'gray')
                    ->description(fn (Pegawai $record) => $record->is_jatuh_tempo_kgb ? '⏳ Jatuh Tempo' : null),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status_kepegawaian')
                    ->label('Status Pegawai')
                    ->options([
                        'PNS' => 'PNS',
                        'PPPK' => 'PPPK',
                        'Honorer' => 'Honorer',
                    ]),

                Tables\Filters\SelectFilter::make('golongan_id')
                    ->label('Golongan')
                    ->relationship('golongan', 'kode'),

                Tables\Filters\Filter::make('calon_kp')
                    ->label('⚡ Calon Kenaikan Pangkat (<= 6 Bulan)')
                    ->query(fn (Builder $query) => $query->calonKp()),

                Tables\Filters\Filter::make('jatuh_tempo_kgb')
                    ->label('⏳ Jatuh Tempo KGB (<= 3 Bulan)')
                    ->query(fn (Builder $query) => $query->whereNotNull('tmt_kgb_terakhir')
                        ->where('tmt_kgb_terakhir', '<=', now()->subYears(2)->addMonths(3))),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status Keaktifan'),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\Action::make('buat_kp')
                        ->label('Ajukan Kenaikan Pangkat')
                        ->icon('heroicon-o-arrow-trending-up')
                        ->color('success')
                        ->authorize(fn (): bool => static::resourcePermission('create'))
                        ->url(fn (Pegawai $record) => route('filament.admin.resources.kenaikan-pangkats.create', ['pegawai_id' => $record->id])),
                    Tables\Actions\Action::make('buat_kgb')
                        ->label('Proses Gaji Berkala')
                        ->icon('heroicon-o-banknotes')
                        ->color('warning')
                        ->authorize(fn (): bool => static::resourcePermission('create'))
                        ->url(fn (Pegawai $record) => route('filament.admin.resources.gaji-berkalas.create', ['pegawai_id' => $record->id])),
                    Tables\Actions\DeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\BulkAction::make('export_csv')
                        ->label('Ekspor CSV')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->authorize(fn (): bool => static::resourcePermission('export'))
                        ->action(function ($records) {
                            static::authorizeResourceAction('export');
                            $headers = [
                                'Content-Type' => 'text/csv',
                                'Content-Disposition' => 'attachment; filename="data-pegawai-' . date('Ymd-His') . '.csv"',
                            ];

                            $callback = function () use ($records) {
                                $handle = fopen('php://output', 'w');
                                fputcsv($handle, ['NIP', 'Nama', 'Email', 'Telepon', 'Status', 'Jabatan', 'Unit Kerja', 'Golongan', 'Pendidikan', 'Mulai Tugas', 'TMT Pangkat Terakhir', 'TMT KGB Terakhir', 'Status Aktif']);
                                foreach ($records as $row) {
                                    fputcsv($handle, [
                                        $row->nip,
                                        $row->nama,
                                        $row->email,
                                        $row->nomor_telepon,
                                        $row->status_kepegawaian,
                                        $row->jabatan,
                                        $row->unit_kerja,
                                        $row->golongan?->kode,
                                        $row->pendidikan_terakhir,
                                        $row->tanggal_mulai_tugas?->format('Y-m-d'),
                                        $row->tmt_pangkat_terakhir?->format('Y-m-d'),
                                        $row->tmt_kgb_terakhir?->format('Y-m-d'),
                                        $row->is_active ? 'Aktif' : 'Non-Aktif',
                                    ]);
                                }
                                fclose($handle);
                            };

                            return Response::stream($callback, 200, $headers);
                        }),
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
            'index' => Pages\ListPegawais::route('/'),
            'create' => Pages\CreatePegawai::route('/create'),
            'edit' => Pages\EditPegawai::route('/{record}/edit'),
        ];
    }
}
