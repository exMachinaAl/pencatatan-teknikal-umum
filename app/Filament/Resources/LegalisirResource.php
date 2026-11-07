<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\AuthorizesModuleAccess;
use App\Filament\Resources\LegalisirResource\Pages;
use App\Models\AuditLog;
use App\Models\Legalisir;
use App\Support\SecureCsv;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Response;

class LegalisirResource extends Resource
{
    use AuthorizesModuleAccess;

    protected static ?string $model = Legalisir::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-check';

    protected static ?string $navigationGroup = 'Layanan Publik';

    protected static ?string $modelLabel = 'Catatan Legalisir';

    protected static ?string $pluralModelLabel = 'Catatan Legalisir';

    protected static ?int $navigationSort = 1;

    protected static function accessModule(): string
    {
        return 'legalisir';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Identitas Pemohon & Dokumen')
                ->description('Legalisir dicatat sekali setelah dilayani. Tidak ada tahap ditolak, diproses, siap, atau diambil di aplikasi.')
                ->schema([
                    Forms\Components\TextInput::make('nomor_legalisir')
                        ->label('Nomor Registrasi')
                        ->helperText('Dihasilkan otomatis saat data disimpan.')
                        ->placeholder('Otomatis saat disimpan')
                        ->disabled()
                        ->dehydrated(false),
                    Forms\Components\DatePicker::make('tanggal_permohonan')
                        ->label('Tanggal Legalisir')
                        ->default(now())
                        ->required()
                        ->displayFormat('d/m/Y'),
                    Forms\Components\TextInput::make('nama_pemohon')
                        ->label('Nama Lengkap Pemohon')
                        ->required()
                        ->maxLength(150),
                    Forms\Components\TextInput::make('kontak')
                        ->label('No. Telepon / WhatsApp')
                        ->tel()
                        ->maxLength(60)
                        ->placeholder('08xxxxxxxxxx'),
                    Forms\Components\Select::make('status_pemohon')
                        ->label('Status Pemohon')
                        ->options(Legalisir::STATUS_PEMOHON)
                        ->default('alumni')
                        ->required(),
                    Forms\Components\Select::make('jenis_dokumen')
                        ->label('Jenis Dokumen')
                        ->options(Legalisir::JENIS_DOKUMEN)
                        ->default('Ijazah')
                        ->required(),
                    Forms\Components\TextInput::make('tahun_lulus')
                        ->label('Tahun Lulus')
                        ->numeric()
                        ->minValue(1950)
                        ->maxValue((int) date('Y')),
                ])->columns(2),

            Forms\Components\Section::make('Jumlah Dokumen & Arsip TU')
                ->description('Maksimal 6 dokumen dibawa. Sistem menghitung jumlah kembali; satu dokumen selalu disimpan sebagai arsip TU.')
                ->schema([
                    Forms\Components\Select::make('jumlah_dibawa')
                        ->label('Jumlah Dibawa')
                        ->options(array_combine(
                            range(1, Legalisir::MAKSIMAL_DOKUMEN_DIBAWA),
                            range(1, Legalisir::MAKSIMAL_DOKUMEN_DIBAWA),
                        ))
                        ->default(2)
                        ->required()
                        ->live()
                        ->afterStateUpdated(function ($state, callable $set): void {
                            $set('jumlah_disimpan', 1);
                            $set('jumlah_dikembalikan', max(0, (int) $state - 1));
                        }),
                    Forms\Components\TextInput::make('jumlah_dikembalikan')
                        ->label('Dikembalikan ke Pemohon')
                        ->default(1)
                        ->disabled()
                        ->dehydrated(),
                    Forms\Components\TextInput::make('jumlah_disimpan')
                        ->label('Disimpan sebagai Arsip TU')
                        ->default(1)
                        ->disabled()
                        ->dehydrated(),
                    Forms\Components\TextInput::make('lokasi_arsip_fisik')
                        ->label('Lokasi Arsip Fisik')
                        ->placeholder('Contoh: Loker 2 / Map Ijazah / LEG-2026')
                        ->helperText('Wajib diisi dan dapat dicari dari tabel maupun pencarian terpadu.')
                        ->required()
                        ->maxLength(150),
                    Forms\Components\FileUpload::make('file_dokumen_path')
                        ->label('Scan Dokumen Arsip (PDF / JPG / PNG)')
                        ->disk('local')
                        ->directory('legalisir_dokumen')
                        ->visibility('private')
                        ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                        ->maxSize(5120)
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('tautan_lampiran')
                        ->label('Tautan Google Drive / Sheets / Lampiran')
                        ->placeholder('https://drive.google.com/...')
                        ->url()
                        ->rules(['nullable', 'starts_with:https://'])
                        ->maxLength(500)
                        ->helperText('Opsional. Gunakan HTTPS dan batasi akses Google untuk dokumen sensitif.')
                        ->columnSpanFull(),
                    Forms\Components\Textarea::make('catatan')
                        ->label('Catatan Petugas')
                        ->placeholder('Contoh: nomor seri ijazah atau keterangan penyimpanan')
                        ->rows(3)
                        ->columnSpanFull(),
                ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ViewColumn::make('mobile_summary')
                    ->label('Ringkasan')
                    ->view('filament.tables.columns.document-mobile-summary', fn (Legalisir $record): array => [
                        'title' => $record->nama_pemohon,
                        'meta' => collect([
                            $record->nomor_legalisir,
                            $record->jenis_dokumen,
                            "Dibawa {$record->jumlah_dibawa}, kembali {$record->jumlah_dikembalikan}, arsip {$record->jumlah_disimpan}",
                            $record->lokasi_arsip_fisik ? "Lokasi {$record->lokasi_arsip_fisik}" : null,
                            $record->tanggal_permohonan?->translatedFormat('d M Y'),
                        ])->filter()->implode(' · '),
                        'externalAttribute' => 'tautan_lampiran',
                    ])
                    ->extraCellAttributes(['class' => 'tu-mobile-summary-cell'])
                    ->hiddenFrom('lg'),
                Tables\Columns\TextColumn::make('nomor_legalisir')
                    ->label('No. Registrasi')
                    ->searchable(['nomor_legalisir', 'nomor_antrean'])
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (Legalisir $record) => $record->tanggal_permohonan?->translatedFormat('d M Y'))
                    ->extraCellAttributes(['class' => 'tu-table-primary'])
                    ->visibleFrom('lg'),
                Tables\Columns\TextColumn::make('nama_pemohon')
                    ->label('Pemohon')
                    ->searchable(['nama_pemohon', 'kontak'])
                    ->sortable()
                    ->weight('semibold')
                    ->description(fn (Legalisir $record): string => collect([
                        Legalisir::STATUS_PEMOHON[$record->status_pemohon] ?? $record->status_pemohon,
                        $record->kontak,
                    ])->filter()->implode(' · ') ?: '-')
                    ->wrap()
                    ->extraCellAttributes(['class' => 'tu-table-primary'])
                    ->visibleFrom('lg'),
                Tables\Columns\TextColumn::make('jenis_dokumen')
                    ->label('Dokumen')
                    ->badge()
                    ->color('info')
                    ->description(fn (Legalisir $record): string => "Dibawa {$record->jumlah_dibawa} · Kembali {$record->jumlah_dikembalikan} · Arsip {$record->jumlah_disimpan}")
                    ->extraCellAttributes(['class' => 'tu-table-secondary'])
                    ->visibleFrom('lg'),
                Tables\Columns\TextColumn::make('lokasi_arsip_fisik')
                    ->label('Lokasi Arsip')
                    ->searchable()
                    ->placeholder('Belum dicatat')
                    ->limit(28)
                    ->wrap()
                    ->extraCellAttributes(['class' => 'tu-table-archive'])
                    ->visibleFrom('lg'),
                Tables\Columns\ViewColumn::make('document_links')
                    ->label('Lampiran')
                    ->view('filament.tables.columns.document-links', ['externalAttribute' => 'tautan_lampiran'])
                    ->extraCellAttributes(['class' => 'tu-document-cell'])
                    ->visibleFrom('lg'),
            ])
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('lampiran'))
            ->defaultSort('tanggal_permohonan', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('jenis_dokumen')
                    ->label('Jenis Dokumen')
                    ->options(Legalisir::JENIS_DOKUMEN),
                Tables\Filters\SelectFilter::make('status_pemohon')
                    ->label('Status Pemohon')
                    ->options(Legalisir::STATUS_PEMOHON),
                Tables\Filters\Filter::make('periode')
                    ->form([
                        Forms\Components\DatePicker::make('dari_tanggal')->label('Dari Tanggal'),
                        Forms\Components\DatePicker::make('sampai_tanggal')->label('Sampai Tanggal'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['dari_tanggal'], fn ($q, $date) => $q->whereDate('tanggal_permohonan', '>=', $date))
                        ->when($data['sampai_tanggal'], fn ($q, $date) => $q->whereDate('tanggal_permohonan', '<=', $date))),
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
                            AuditLog::record('export', 'Legalisir', null, null, ['jumlah_record' => $records->count()]);
                            $headers = [
                                'Content-Type' => 'text/csv',
                                'Content-Disposition' => 'attachment; filename="legalisir-'.date('Ymd-His').'.csv"',
                            ];

                            return Response::stream(function () use ($records): void {
                                $handle = fopen('php://output', 'w');
                                SecureCsv::writeRow($handle, ['No Registrasi', 'Tanggal', 'Nama Pemohon', 'Kontak', 'Status Pemohon', 'Jenis Dokumen', 'Tahun Lulus', 'Jumlah Dibawa', 'Jumlah Dikembalikan', 'Jumlah Disimpan', 'Lokasi Arsip', 'Tautan Lampiran', 'Catatan']);
                                foreach ($records as $row) {
                                    SecureCsv::writeRow($handle, [
                                        $row->nomor_legalisir,
                                        $row->tanggal_permohonan?->format('Y-m-d'),
                                        $row->nama_pemohon,
                                        $row->kontak,
                                        $row->status_pemohon,
                                        $row->jenis_dokumen,
                                        $row->tahun_lulus,
                                        $row->jumlah_dibawa,
                                        $row->jumlah_dikembalikan,
                                        $row->jumlah_disimpan,
                                        $row->lokasi_arsip_fisik,
                                        $row->tautan_lampiran,
                                        $row->catatan,
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
            'index' => Pages\ListLegalisirs::route('/'),
            'create' => Pages\CreateLegalisir::route('/create'),
            'view' => Pages\ViewLegalisir::route('/{record}'),
            'edit' => Pages\EditLegalisir::route('/{record}/edit'),
        ];
    }
}
