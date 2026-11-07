<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\AuthorizesModuleAccess;
use App\Filament\Resources\SuratMasukResource\Pages;
use App\Models\AuditLog;
use App\Models\RefKlasifikasiSurat;
use App\Models\SuratMasuk;
use App\Support\SecureCsv;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Response;

class SuratMasukResource extends Resource
{
    use AuthorizesModuleAccess;

    protected static ?string $model = SuratMasuk::class;

    protected static ?string $navigationIcon = 'heroicon-o-inbox-arrow-down';

    protected static ?string $navigationGroup = 'Surat & Kearsipan';

    protected static ?string $modelLabel = 'Surat Masuk';

    protected static ?string $pluralModelLabel = 'Surat Masuk';

    protected static ?int $navigationSort = 1;

    protected static function accessModule(): string
    {
        return 'surat';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Registrasi & Agenda Surat')
                    ->schema([
                        Forms\Components\TextInput::make('nomor_agenda')
                            ->label('Nomor Agenda')
                            ->helperText('Dihasilkan otomatis ketika data disimpan dan tidak dapat diubah.')
                            ->placeholder('Otomatis saat disimpan')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\DatePicker::make('tanggal_diterima')
                            ->label('Tanggal Diterima TU')
                            ->default(now())
                            ->required()
                            ->displayFormat('d/m/Y'),

                        Forms\Components\TextInput::make('nomor_surat')
                            ->label('Nomor Surat Asal')
                            ->placeholder('Nomor yang tertera pada surat')
                            ->maxLength(100),

                        Forms\Components\DatePicker::make('tanggal_surat')
                            ->label('Tanggal Surat Asal')
                            ->displayFormat('d/m/Y'),

                        Forms\Components\TextInput::make('pengirim')
                            ->label('Pengirim / Instansi Asal')
                            ->placeholder('Contoh: Dinas Pendidikan, Puskesmas, Polsek')
                            ->required()
                            ->maxLength(200)
                            ->columnSpanFull(),

                    ])->columns(2),

                Forms\Components\Section::make('Klasifikasi & Lokasi Arsip')
                    ->description('Catat lokasi fisik terlebih dahulu. Lampiran digital bersifat opsional, disimpan privat, dan setiap aksesnya dicatat.')
                    ->schema([
                        Forms\Components\Select::make('klasifikasi_id')
                            ->label('Kode Klasifikasi Surat')
                            ->relationship('klasifikasi', 'kode')
                            ->getOptionLabelFromRecordUsing(fn (RefKlasifikasiSurat $record) => "{$record->kode} - {$record->nama}")
                            ->searchable(['kode', 'nama'])
                            ->preload()
                            ->helperText('Ketik kode atau nama untuk mencari. Jika belum tersedia, tambahkan kode baru dari tombol pada input ini.')
                            ->createOptionForm([
                                Forms\Components\TextInput::make('kode')
                                    ->label('Kode Klasifikasi Baru')
                                    ->required()
                                    ->maxLength(30),
                                Forms\Components\TextInput::make('nama')
                                    ->label('Nama Klasifikasi')
                                    ->maxLength(150)
                                    ->helperText('Opsional. Jika kosong, nama akan mengikuti kode.'),
                            ])
                            ->createOptionUsing(function (array $data): int {
                                $kode = trim((string) $data['kode']);
                                $nama = trim((string) ($data['nama'] ?? ''));

                                return (int) RefKlasifikasiSurat::firstOrCreate(
                                    ['kode' => $kode],
                                    ['nama' => $nama !== '' ? $nama : $kode],
                                )->getKey();
                            }),

                        Forms\Components\TextInput::make('lokasi_arsip_fisik')
                            ->label('Lokasi Arsip Fisik')
                            ->placeholder('Contoh: Lemari A / Rak 2 / Ordner 2026')
                            ->helperText('Wajib diisi agar surat dapat ditemukan kembali tanpa membuka berkas satu per satu.')
                            ->required()
                            ->maxLength(150),

                        Forms\Components\FileUpload::make('file_path')
                            ->label('Softfile Scan Surat (PDF / JPG / PNG)')
                            ->disk('local')
                            ->directory('arsip_surat_masuk')
                            ->visibility('private')
                            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                            ->maxSize(5120) // Maksimal 5 MB
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('tautan_gform')
                            ->label('Tautan Google Drive / Sheets / Lampiran')
                            ->placeholder('https://drive.google.com/... atau https://docs.google.com/spreadsheets/...')
                            ->url()
                            ->rules(['nullable', 'starts_with:https://'])
                            ->maxLength(500)
                            ->helperText('Opsional. Gunakan tautan HTTPS dan batasi akses di Google untuk dokumen sensitif.')
                            ->columnSpanFull(),
                    ])->columns(2),

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ViewColumn::make('mobile_summary')
                    ->label('Ringkasan')
                    ->view('filament.tables.columns.document-mobile-summary', fn (SuratMasuk $record): array => [
                        'title' => $record->pengirim,
                        'meta' => collect([
                            $record->nomor_surat ?: 'Tanpa nomor',
                            $record->klasifikasi?->kode ? "Klasifikasi {$record->klasifikasi->kode}" : null,
                            $record->tanggal_diterima?->translatedFormat('d M Y'),
                            $record->lokasi_arsip_fisik ? "Arsip {$record->lokasi_arsip_fisik}" : null,
                        ])->filter()->implode(' · '),
                        'externalAttribute' => 'tautan_gform',
                    ])
                    ->extraCellAttributes(['class' => 'tu-mobile-summary-cell'])
                    ->hiddenFrom('lg'),

                Tables\Columns\TextColumn::make('nomor_surat')
                    ->label('No. Surat Asal')
                    ->placeholder('Tanpa nomor')
                    ->searchable(['nomor_surat', 'nomor_agenda'])
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (SuratMasuk $record) => "Agenda: {$record->nomor_agenda}")
                    ->extraAttributes(['class' => 'tu-table-primary'])
                    ->visibleFrom('lg'),

                Tables\Columns\TextColumn::make('pengirim')
                    ->label('Pengirim')
                    ->searchable()
                    ->weight('semibold')
                    ->limit(28)
                    ->wrap()
                    ->description(fn (SuratMasuk $record) => $record->tanggal_diterima?->translatedFormat('d M Y'))
                    ->extraAttributes(['class' => 'tu-table-primary'])
                    ->visibleFrom('lg'),

                Tables\Columns\TextColumn::make('klasifikasi.kode')
                    ->label('Kode Klasifikasi')
                    ->searchable(['kode', 'nama'])
                    ->placeholder('-')
                    ->visibleFrom('lg'),

                Tables\Columns\TextColumn::make('lokasi_arsip_fisik')
                    ->label('Lokasi Fisik')
                    ->searchable()
                    ->limit(28)
                    ->wrap()
                    ->placeholder('Belum dicatat')
                    ->extraAttributes(['class' => 'tu-table-archive'])
                    ->visibleFrom('lg'),

                Tables\Columns\ViewColumn::make('document_links')
                    ->label('Lampiran')
                    ->view('filament.tables.columns.document-links', ['externalAttribute' => 'tautan_gform'])
                    ->extraCellAttributes(['class' => 'tu-document-cell'])
                    ->visibleFrom('lg'),
            ])
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['lampiran', 'klasifikasi']))
            ->defaultSort('tanggal_diterima', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('klasifikasi_id')
                    ->label('Klasifikasi')
                    ->relationship('klasifikasi', 'kode')
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('periode_diterima')
                    ->form([
                        Forms\Components\DatePicker::make('dari_tanggal')->label('Dari Tanggal'),
                        Forms\Components\DatePicker::make('sampai_tanggal')->label('Sampai Tanggal'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query
                            ->when($data['dari_tanggal'], fn ($q, $d) => $q->whereDate('tanggal_diterima', '>=', $d))
                            ->when($data['sampai_tanggal'], fn ($q, $d) => $q->whereDate('tanggal_diterima', '<=', $d));
                    }),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make()
                        ->label('Lihat detail'),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('export_csv')
                        ->label('Ekspor CSV')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->authorize(fn (): bool => static::resourcePermission('export'))
                        ->action(function ($records) {
                            static::authorizeResourceAction('export');
                            AuditLog::record('export', 'SuratMasuk', null, null, ['jumlah_record' => $records->count()]);
                            $headers = [
                                'Content-Type' => 'text/csv',
                                'Content-Disposition' => 'attachment; filename="surat-masuk-'.date('Ymd-His').'.csv"',
                            ];

                            $callback = function () use ($records) {
                                $handle = fopen('php://output', 'w');
                                SecureCsv::writeRow($handle, ['No Agenda', 'No Surat Asal', 'Tgl Surat', 'Tgl Diterima', 'Pengirim', 'Kode Klasifikasi', 'Lokasi Arsip', 'Tautan Lampiran']);
                                foreach ($records as $row) {
                                    SecureCsv::writeRow($handle, [
                                        $row->nomor_agenda,
                                        $row->nomor_surat,
                                        $row->tanggal_surat?->format('Y-m-d'),
                                        $row->tanggal_diterima?->format('Y-m-d'),
                                        $row->pengirim,
                                        $row->klasifikasi?->kode,
                                        $row->lokasi_arsip_fisik,
                                        $row->tautan_gform,
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
            'index' => Pages\ListSuratMasuks::route('/'),
            'create' => Pages\CreateSuratMasuk::route('/create'),
            'view' => Pages\ViewSuratMasuk::route('/{record}'),
            'edit' => Pages\EditSuratMasuk::route('/{record}/edit'),
        ];
    }
}
