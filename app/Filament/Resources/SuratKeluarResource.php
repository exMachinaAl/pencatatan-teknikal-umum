<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\AuthorizesModuleAccess;
use App\Filament\Resources\SuratKeluarResource\Pages;
use App\Models\AuditLog;
use App\Models\RefKlasifikasiSurat;
use App\Models\SuratKeluar;
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
        return $form->schema([
            Forms\Components\Section::make('Identitas Surat Keluar')
                ->description('Surat langsung dicatat sebagai arsip. Tidak ada tahapan draf, pengiriman, atau penerimaan di aplikasi.')
                ->schema([
                    Forms\Components\TextInput::make('nomor_surat')
                        ->label('Nomor Surat')
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
                        ->placeholder('Contoh: Dinas Pendidikan atau orang tua siswa')
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
                        ->helperText('Cari kode/nama atau tambahkan kode baru jika belum tersedia.')
                        ->createOptionForm([
                            Forms\Components\TextInput::make('kode')->label('Kode Klasifikasi Baru')->required()->maxLength(30),
                            Forms\Components\TextInput::make('nama')->label('Nama Klasifikasi')->maxLength(150),
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
                        ->placeholder('Contoh: Lemari B / Boks Surat Keluar 2026')
                        ->helperText('Wajib diisi agar surat dapat ditemukan kembali tanpa membuka berkas satu per satu.')
                        ->required()
                        ->maxLength(150),
                    Forms\Components\FileUpload::make('file_final_path')
                        ->label('Softfile Final / Scan (PDF / JPG / PNG)')
                        ->disk('local')
                        ->directory('arsip_surat_keluar')
                        ->visibility('private')
                        ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                        ->maxSize(5120)
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('tautan_gform')
                        ->label('Tautan Google Drive / Sheets / Lampiran')
                        ->placeholder('https://drive.google.com/...')
                        ->url()
                        ->rules(['nullable', 'starts_with:https://'])
                        ->maxLength(500)
                        ->helperText('Opsional. Gunakan HTTPS dan batasi akses Google untuk dokumen sensitif.')
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
                    ->view('filament.tables.columns.document-mobile-summary', fn (SuratKeluar $record): array => [
                        'title' => $record->tujuan,
                        'meta' => collect([
                            $record->nomor_surat ?: 'Tanpa nomor',
                            $record->klasifikasi?->kode ? "Klasifikasi {$record->klasifikasi->kode}" : null,
                            $record->tanggal_surat?->translatedFormat('d M Y'),
                            $record->lokasi_arsip_fisik ? "Arsip {$record->lokasi_arsip_fisik}" : null,
                        ])->filter()->implode(' · '),
                        'externalAttribute' => 'tautan_gform',
                    ])
                    ->extraCellAttributes(['class' => 'tu-mobile-summary-cell'])
                    ->hiddenFrom('lg'),
                Tables\Columns\TextColumn::make('nomor_surat')
                    ->label('Nomor Surat')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->extraAttributes(['class' => 'tu-table-primary'])
                    ->visibleFrom('lg'),
                Tables\Columns\TextColumn::make('tujuan')
                    ->label('Tujuan')
                    ->searchable()
                    ->weight('semibold')
                    ->limit(28)
                    ->wrap()
                    ->description(fn (SuratKeluar $record) => $record->tanggal_surat?->translatedFormat('d M Y'))
                    ->extraAttributes(['class' => 'tu-table-primary'])
                    ->visibleFrom('lg'),
                Tables\Columns\TextColumn::make('klasifikasi.kode')
                    ->label('Klasifikasi')
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
            ->defaultSort('tanggal_surat', 'desc')
            ->filters([
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
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['dari_tanggal'], fn ($q, $date) => $q->whereDate('tanggal_surat', '>=', $date))
                        ->when($data['sampai_tanggal'], fn ($q, $date) => $q->whereDate('tanggal_surat', '<=', $date))),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make()->label('Lihat detail'),
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
                            AuditLog::record('export', 'SuratKeluar', null, null, ['jumlah_record' => $records->count()]);
                            $headers = [
                                'Content-Type' => 'text/csv',
                                'Content-Disposition' => 'attachment; filename="surat-keluar-'.date('Ymd-His').'.csv"',
                            ];

                            return Response::stream(function () use ($records): void {
                                $handle = fopen('php://output', 'w');
                                SecureCsv::writeRow($handle, ['Nomor Surat', 'Tanggal', 'Tujuan', 'Kode Klasifikasi', 'Lokasi Arsip', 'Tautan Lampiran']);
                                foreach ($records as $row) {
                                    SecureCsv::writeRow($handle, [
                                        $row->nomor_surat,
                                        $row->tanggal_surat?->format('Y-m-d'),
                                        $row->tujuan,
                                        $row->klasifikasi?->kode,
                                        $row->lokasi_arsip_fisik,
                                        $row->tautan_gform,
                                    ]);
                                }
                                fclose($handle);
                            }, 200, $headers);
                        }),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
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
