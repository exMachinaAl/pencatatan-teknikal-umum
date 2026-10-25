<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\AuthorizesModuleAccess;
use App\Filament\Resources\SuratKeluarResource\Pages;
use App\Models\AuditLog;
use App\Models\RefKlasifikasiSurat;
use App\Models\SuratKeluar;
use App\Support\ConcurrentRecordUpdate;
use App\Support\SecureCsv;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Response;

class SuratKeluarResource extends Resource
{
    use AuthorizesModuleAccess;

    protected static ?string $model = SuratKeluar::class;

    protected static ?string $navigationIcon = 'heroicon-o-paper-airplane';

    protected static ?string $navigationGroup = 'Surat & Kearsipan';

    protected static ?string $modelLabel = 'Surat Keluar';

    protected static ?string $pluralModelLabel = 'Surat Keluar';

    protected static ?int $navigationSort = 2;

    protected static function accessModule(): string
    {
        return 'surat';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Penomoran & Tanggal Surat')
                    ->schema([
                        Forms\Components\TextInput::make('nomor_surat')
                            ->label('Nomor Surat Keluar')
                            ->helperText('Isi sesuai nomor resmi yang telah ditetapkan sekolah.')
                            ->placeholder('Contoh: 421.1/001/SMAN1/2026')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(100),

                        Forms\Components\DatePicker::make('tanggal_surat')
                            ->label('Tanggal Surat')
                            ->default(now())
                            ->required()
                            ->displayFormat('d/m/Y'),

                        Forms\Components\TextInput::make('tujuan')
                            ->label('Tujuan Surat / Penerima')
                            ->placeholder('Contoh: Kepala Dinas Pendidikan Provinsi, Orang Tua Siswa')
                            ->required()
                            ->maxLength(200)
                            ->columnSpanFull(),

                    ])->columns(2),

                Forms\Components\Section::make('Klasifikasi & Arsip Digital')
                    ->description('Bagian sensitif: lampiran disimpan privat, terikat pada record ini, dan setiap akses unduh/pratinjau dicatat.')
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
                            ->placeholder('Contoh: Boks Surat Keluar 2026'),

                        Forms\Components\FileUpload::make('file_final_path')
                            ->label('Softfile Final / Scan Bertanda Tangan (PDF / JPG / PNG)')
                            ->disk('local')
                            ->directory('arsip_surat_keluar')
                            ->visibility('private')
                            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                            ->maxSize(5120)
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

                Forms\Components\Section::make('Relasi Surat & Pengiriman')
                    ->description('Hubungkan surat balasan dan catat bukti pengiriman.')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Forms\Components\Select::make('suratMasukTerkait')
                            ->label('Membalas Surat Masuk')
                            ->relationship('suratMasukTerkait', 'nomor_surat')
                            ->getOptionLabelFromRecordUsing(fn ($record) => ($record->nomor_surat ?: 'Tanpa nomor surat').' — '.$record->pengirim." (Agenda: {$record->nomor_agenda})")
                            ->multiple()
                            ->searchable(['nomor_surat', 'nomor_agenda', 'pengirim'])
                            ->preload()
                            ->columnSpanFull(),
                        Forms\Components\Select::make('metode_pengiriman')
                            ->label('Metode Pengiriman')
                            ->options(['kurir' => 'Kurir', 'pos' => 'Pos', 'email' => 'Email', 'diambil' => 'Diambil langsung', 'lainnya' => 'Lainnya']),
                        Forms\Components\TextInput::make('nomor_resi')
                            ->label('Nomor Resi / Referensi'),
                        Forms\Components\DateTimePicker::make('dikirim_pada')
                            ->label('Dikirim Pada')
                            ->seconds(false),
                        Forms\Components\DateTimePicker::make('diterima_pada')
                            ->label('Diterima Pada')
                            ->seconds(false),
                        Forms\Components\TextInput::make('diterima_oleh')
                            ->label('Diterima Oleh'),
                        Forms\Components\FileUpload::make('bukti_pengiriman_path')
                            ->label('Bukti Pengiriman / Penerimaan')
                            ->disk('local')
                            ->directory('bukti_pengiriman')
                            ->visibility('private')
                            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                            ->maxSize(5120),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ViewColumn::make('mobile_summary')
                    ->label('Ringkasan')
                    ->view('filament.tables.columns.document-mobile-summary', fn (SuratKeluar $record): array => [
                        'title' => $record->tujuan,
                        'meta' => collect([
                            $record->nomor_surat ?: 'Tanpa nomor',
                            $record->klasifikasi?->kode ? "Klasifikasi {$record->klasifikasi->kode}" : null,
                            $record->tanggal_surat?->translatedFormat('d M Y'),
                            SuratKeluar::STATUSES[$record->status] ?? $record->status,
                        ])->filter()->implode(' · '),
                        'externalAttribute' => 'tautan_gform',
                    ])
                    ->extraCellAttributes(['class' => 'tu-mobile-summary-cell'])
                    ->hiddenFrom('md'),

                Tables\Columns\TextColumn::make('nomor_surat')
                    ->label('Nomor Surat')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->visibleFrom('md'),

                Tables\Columns\TextColumn::make('tujuan')
                    ->label('Tujuan')
                    ->searchable()
                    ->weight('semibold')
                    ->limit(28)
                    ->wrap()
                    ->description(fn (SuratKeluar $record) => $record->tanggal_surat?->translatedFormat('d M Y'))
                    ->visibleFrom('md'),

                Tables\Columns\TextColumn::make('klasifikasi.kode')
                    ->label('Kode Klasifikasi')
                    ->searchable(['kode', 'nama'])
                    ->placeholder('-')
                    ->visibleFrom('md'),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'warning' => 'draf',
                        'info' => 'terkirim',
                        'gray' => 'diarsipkan',
                    ])
                    ->formatStateUsing(fn ($state) => SuratKeluar::STATUSES[$state] ?? $state)
                    ->sortable()
                    ->visibleFrom('md'),

                Tables\Columns\ViewColumn::make('document_links')
                    ->label('Lampiran')
                    ->view('filament.tables.columns.document-links', ['externalAttribute' => 'tautan_gform'])
                    ->extraCellAttributes(['class' => 'tu-document-cell'])
                    ->visibleFrom('md'),
            ])
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['lampiran', 'klasifikasi']))
            ->defaultSort('tanggal_surat', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status Surat')
                    ->options(SuratKeluar::STATUSES),

                Tables\Filters\SelectFilter::make('klasifikasi_id')
                    ->label('Klasifikasi')
                    ->relationship('klasifikasi', 'kode')
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('periode_surat')
                    ->form([
                        Forms\Components\DatePicker::make('dari_tanggal')->label('Dari Tanggal'),
                        Forms\Components\DatePicker::make('sampai_tanggal')->label('Sampai Tanggal'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query
                            ->when($data['dari_tanggal'], fn ($q, $d) => $q->whereDate('tanggal_surat', '>=', $d))
                            ->when($data['sampai_tanggal'], fn ($q, $d) => $q->whereDate('tanggal_surat', '<=', $d));
                    }),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make()
                        ->label('Lihat detail'),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\Action::make('tandai_terkirim')
                        ->label('Tandai Terkirim')
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->authorize(fn (): bool => static::resourcePermission('transition'))
                        ->visible(fn (SuratKeluar $record): bool => $record->status === 'draf')
                        ->form([
                            Forms\Components\Select::make('metode_pengiriman')
                                ->label('Metode Pengiriman')
                                ->options(['kurir' => 'Kurir', 'pos' => 'Pos', 'email' => 'Email', 'diambil' => 'Diambil langsung', 'lainnya' => 'Lainnya'])
                                ->default('lainnya'),
                            Forms\Components\TextInput::make('nomor_resi')->label('Nomor Resi / Referensi'),
                            Forms\Components\DateTimePicker::make('dikirim_pada')->label('Waktu Pengiriman')->default(now())->seconds(false),
                            Forms\Components\FileUpload::make('bukti_pengiriman_path')
                                ->label('Bukti Pengiriman')
                                ->disk('local')->directory('bukti_pengiriman')->visibility('private')
                                ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])->maxSize(5120),
                        ])
                        ->action(function (SuratKeluar $record, array $data) {
                            static::authorizeResourceAction('transition');
                            ConcurrentRecordUpdate::run($record, fn (SuratKeluar $locked) => $locked->update([
                                'status' => 'terkirim',
                                'metode_pengiriman' => $data['metode_pengiriman'] ?? 'lainnya',
                                'nomor_resi' => $data['nomor_resi'] ?? null,
                                'dikirim_pada' => $data['dikirim_pada'] ?? now(),
                                'bukti_pengiriman_path' => $data['bukti_pengiriman_path'] ?? null,
                            ]));
                        })
                        ->after(fn ($livewire) => $livewire->resetTable()),
                    Tables\Actions\Action::make('arsipkan')
                        ->label('Tandai Diarsipkan')
                        ->icon('heroicon-o-archive-box')
                        ->color('gray')
                        ->authorize(fn (): bool => static::resourcePermission('transition'))
                        ->visible(fn (SuratKeluar $record): bool => $record->status === 'terkirim')
                        ->form([
                            Forms\Components\TextInput::make('lokasi_arsip_fisik')
                                ->label('Lokasi Arsip Fisik')
                                ->default(fn (SuratKeluar $record) => $record->lokasi_arsip_fisik)
                                ->required(),
                        ])
                        ->action(function (SuratKeluar $record, array $data) {
                            static::authorizeResourceAction('transition');
                            ConcurrentRecordUpdate::run($record, fn (SuratKeluar $locked) => $locked->update([
                                'status' => 'diarsipkan',
                                'lokasi_arsip_fisik' => $data['lokasi_arsip_fisik'],
                                'diarsipkan_pada' => now(),
                            ]));
                        })
                        ->after(fn ($livewire) => $livewire->resetTable()),
                    Tables\Actions\Action::make('catat_diterima')
                        ->label('Catat Bukti Diterima')
                        ->icon('heroicon-o-clipboard-document-check')
                        ->color('info')
                        ->authorize(fn (): bool => static::resourcePermission('transition'))
                        ->visible(fn (SuratKeluar $record): bool => $record->status === 'terkirim' && ! $record->diterima_pada)
                        ->form([
                            Forms\Components\TextInput::make('diterima_oleh')->label('Diterima Oleh')->required(),
                            Forms\Components\DateTimePicker::make('diterima_pada')->label('Waktu Diterima')->default(now())->required()->seconds(false),
                            Forms\Components\FileUpload::make('bukti_pengiriman_path')
                                ->label('Bukti Diterima')
                                ->disk('local')->directory('bukti_pengiriman')->visibility('private')
                                ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])->maxSize(5120),
                        ])
                        ->action(function (SuratKeluar $record, array $data) {
                            static::authorizeResourceAction('transition');
                            ConcurrentRecordUpdate::run($record, fn (SuratKeluar $locked) => $locked->update($data));
                        })
                        ->after(fn ($livewire) => $livewire->resetTable()),
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
                            AuditLog::record('export', 'SuratKeluar', null, null, ['jumlah_record' => $records->count()]);
                            $headers = [
                                'Content-Type' => 'text/csv',
                                'Content-Disposition' => 'attachment; filename="surat-keluar-'.date('Ymd-His').'.csv"',
                            ];

                            $callback = function () use ($records) {
                                $handle = fopen('php://output', 'w');
                                SecureCsv::writeRow($handle, ['Nomor Surat', 'Tanggal', 'Tujuan', 'Kode Klasifikasi', 'Status', 'Lokasi Arsip']);
                                foreach ($records as $row) {
                                    SecureCsv::writeRow($handle, [
                                        $row->nomor_surat,
                                        $row->tanggal_surat?->format('Y-m-d'),
                                        $row->tujuan,
                                        $row->klasifikasi?->kode,
                                        $row->status,
                                        $row->lokasi_arsip_fisik,
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
            'index' => Pages\ListSuratKeluars::route('/'),
            'create' => Pages\CreateSuratKeluar::route('/create'),
            'view' => Pages\ViewSuratKeluar::route('/{record}'),
            'edit' => Pages\EditSuratKeluar::route('/{record}/edit'),
        ];
    }
}
