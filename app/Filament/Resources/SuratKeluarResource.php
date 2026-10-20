<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\AuthorizesModuleAccess;
use App\Filament\Resources\SuratKeluarResource\Pages;
use App\Models\RefKlasifikasiSurat;
use App\Models\SuratKeluar;
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

                        Forms\Components\Textarea::make('perihal')
                            ->label('Perihal Surat')
                            ->required()
                            ->rows(3)
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('Klasifikasi & Pejabat')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Forms\Components\Select::make('klasifikasi_id')
                            ->label('Klasifikasi Surat')
                            ->relationship('klasifikasi', 'kode')
                            ->getOptionLabelFromRecordUsing(fn (RefKlasifikasiSurat $record) => "{$record->kode} - {$record->nama}")
                            ->searchable()
                            ->preload(),

                        Forms\Components\Select::make('sifat')
                            ->label('Sifat Surat')
                            ->options(SuratKeluar::SIFAT)
                            ->default('biasa')
                            ->required(),

                        Forms\Components\TextInput::make('penandatangan')
                            ->label('Penandatangan')
                            ->default('Drs. H. Mulyadi, M.Pd (Kepala Sekolah)'),

                        Forms\Components\TextInput::make('pembuat_konsep')
                            ->label('Pembuat Konsep / Pengetik')
                            ->placeholder('Nama staf yang mengetik draf'),

                        Forms\Components\Select::make('status')
                            ->label('Status Surat')
                            ->options(SuratKeluar::STATUSES)
                            ->default('draf')
                            ->required(),

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
                            ->getOptionLabelFromRecordUsing(fn ($record) => ($record->nomor_surat ?: 'Tanpa nomor surat').' — '.$record->pengirim.' — '.$record->perihal." (Agenda: {$record->nomor_agenda})")
                            ->multiple()
                            ->searchable(['nomor_surat', 'nomor_agenda', 'pengirim', 'perihal'])
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
                        'title' => $record->perihal,
                        'meta' => collect([
                            $record->nomor_surat ?: 'Tanpa nomor',
                            $record->tujuan,
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

                Tables\Columns\TextColumn::make('perihal')
                    ->label('Perihal')
                    ->searchable()
                    ->limit(48)
                    ->wrap()
                    ->visibleFrom('md'),

                Tables\Columns\TextColumn::make('tujuan')
                    ->label('Tujuan')
                    ->searchable()
                    ->weight('semibold')
                    ->limit(28)
                    ->wrap()
                    ->description(fn (SuratKeluar $record) => $record->tanggal_surat?->translatedFormat('d M Y'))
                    ->visibleFrom('md'),

                Tables\Columns\BadgeColumn::make('sifat')
                    ->label('Sifat')
                    ->colors([
                        'gray' => 'biasa',
                        'warning' => 'penting',
                        'danger' => 'segera',
                        'primary' => 'rahasia',
                    ])
                    ->formatStateUsing(fn ($state) => SuratKeluar::SIFAT[$state] ?? $state)
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
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('lampiran'))
            ->defaultSort('tanggal_surat', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status Surat')
                    ->options(SuratKeluar::STATUSES),

                Tables\Filters\SelectFilter::make('sifat')
                    ->label('Sifat Surat')
                    ->options(SuratKeluar::SIFAT),

                Tables\Filters\SelectFilter::make('klasifikasi_id')
                    ->label('Klasifikasi')
                    ->relationship('klasifikasi', 'kode'),

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
                            $record->update([
                                'status' => 'terkirim',
                                'metode_pengiriman' => $data['metode_pengiriman'] ?? 'lainnya',
                                'nomor_resi' => $data['nomor_resi'] ?? null,
                                'dikirim_pada' => $data['dikirim_pada'] ?? now(),
                                'bukti_pengiriman_path' => $data['bukti_pengiriman_path'] ?? null,
                            ]);
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
                            $record->update([
                                'status' => 'diarsipkan',
                                'lokasi_arsip_fisik' => $data['lokasi_arsip_fisik'],
                                'diarsipkan_pada' => now(),
                            ]);
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
                            $record->update($data);
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
                            $headers = [
                                'Content-Type' => 'text/csv',
                                'Content-Disposition' => 'attachment; filename="surat-keluar-'.date('Ymd-His').'.csv"',
                            ];

                            $callback = function () use ($records) {
                                $handle = fopen('php://output', 'w');
                                fputcsv($handle, ['Nomor Surat', 'Tanggal', 'Tujuan', 'Perihal', 'Sifat', 'Penandatangan', 'Status', 'Lokasi Arsip']);
                                foreach ($records as $row) {
                                    fputcsv($handle, [
                                        $row->nomor_surat,
                                        $row->tanggal_surat?->format('Y-m-d'),
                                        $row->tujuan,
                                        $row->perihal,
                                        $row->sifat,
                                        $row->penandatangan,
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
