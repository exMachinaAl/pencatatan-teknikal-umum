<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LegalisirResource\Pages;
use App\Models\Legalisir;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;

class LegalisirResource extends Resource
{
    protected static ?string $model = Legalisir::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-check';

    protected static ?string $navigationGroup = 'Layanan Publik';

    protected static ?string $modelLabel = 'Legalisir Dokumen';

    protected static ?string $pluralModelLabel = 'Layanan Legalisir';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Identitas Pemohon & Dokumen')
                    ->schema([
                        Forms\Components\TextInput::make('nomor_legalisir')
                            ->label('Nomor Registrasi Legalisir')
                            ->default(fn () => Legalisir::generateNomorLegalisir())
                            ->required()
                            ->unique(ignoreRecord: true),

                        Forms\Components\DatePicker::make('tanggal_permohonan')
                            ->label('Tanggal Permohonan')
                            ->default(now())
                            ->required()
                            ->displayFormat('d/m/Y'),

                        Forms\Components\TextInput::make('nama_pemohon')
                            ->label('Nama Lengkap Pemohon')
                            ->placeholder('Nama alumni / siswa / wali')
                            ->required()
                            ->maxLength(150),

                        Forms\Components\TextInput::make('kontak')
                            ->label('No. Telepon / WhatsApp')
                            ->tel()
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

                        Forms\Components\TextInput::make('jumlah_lembar')
                            ->label('Jumlah Lembar / Rangkap')
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->required(),

                        Forms\Components\TextInput::make('biaya')
                            ->label('Biaya Administrasi (Rp)')
                            ->numeric()
                            ->default(0)
                            ->prefix('Rp')
                            ->helperText('Gratis (Rp 0) untuk layanan sekolah negeri standar'),
                    ])->columns(2),

                Forms\Components\Section::make('Status Pemrosesan & Pengambilan')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->label('Status Alur Legalisir')
                            ->options(Legalisir::STATUSES)
                            ->default('diterima')
                            ->required()
                            ->reactive(),

                        Forms\Components\DatePicker::make('tanggal_selesai')
                            ->label('Tanggal Selesai / Siap Diambil')
                            ->displayFormat('d/m/Y'),

                        Forms\Components\DatePicker::make('tanggal_diambil')
                            ->label('Tanggal Penyerahan / Diambil')
                            ->displayFormat('d/m/Y'),

                        Forms\Components\TextInput::make('pengambil')
                            ->label('Nama Penerima / Pengambil Dokumen')
                            ->placeholder('Nama yang mengambil (jika diwakilkan)'),

                        Forms\Components\FileUpload::make('file_dokumen_path')
                            ->label('Scan Dokumen Asli / Berkas Verifikasi (PDF/JPG/PNG)')
                            ->disk('local')
                            ->directory('legalisir_dokumen')
                            ->visibility('private')
                            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                            ->maxSize(5120)
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('catatan')
                            ->label('Catatan Petugas (Tahun Lulus, No Seri Ijazah, dll)')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nomor_legalisir')
                    ->label('No. Registrasi')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('nama_pemohon')
                    ->label('Nama Pemohon')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold')
                    ->description(fn (Legalisir $record) => $record->kontak ?: '-'),

                Tables\Columns\BadgeColumn::make('status_pemohon')
                    ->label('Pemohon')
                    ->colors([
                        'primary' => 'alumni',
                        'success' => 'siswa',
                        'warning' => 'wali',
                        'gray' => 'lainnya',
                    ])
                    ->formatStateUsing(fn ($state) => Legalisir::STATUS_PEMOHON[$state] ?? $state),

                Tables\Columns\TextColumn::make('jenis_dokumen')
                    ->label('Dokumen')
                    ->badge()
                    ->color('info')
                    ->description(fn (Legalisir $record) => "{$record->jumlah_lembar} Lembar"),

                Tables\Columns\TextColumn::make('tanggal_permohonan')
                    ->label('Tgl Permohonan')
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'info' => 'diterima',
                        'warning' => 'diproses',
                        'success' => 'siap_diambil',
                        'primary' => 'diambil',
                        'danger' => 'ditolak',
                    ])
                    ->formatStateUsing(fn ($state) => Legalisir::STATUSES[$state] ?? $state)
                    ->sortable(),

                Tables\Columns\TextColumn::make('pengambil')
                    ->label('Diambil Oleh')
                    ->placeholder('Belum diambil')
                    ->limit(20),
            ])
            ->filters([
                Tables\Filters\Filter::make('perlu_tindak_lanjut')
                    ->label('Menunggu verifikasi / proses')
                    ->query(fn (Builder $query) => $query->perluTindakLanjut()),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Status Legalisir')
                    ->options(Legalisir::STATUSES),

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
                    ->query(function (Builder $query, array $data) {
                        return $query
                            ->when($data['dari_tanggal'], fn ($q, $d) => $q->whereDate('tanggal_permohonan', '>=', $d))
                            ->when($data['sampai_tanggal'], fn ($q, $d) => $q->whereDate('tanggal_permohonan', '<=', $d));
                    }),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\Action::make('cetak_bukti')
                        ->label('Cetak Bukti Penerimaan')
                        ->icon('heroicon-o-printer')
                        ->color('success')
                        ->url(fn (Legalisir $record) => route('legalisir.cetak-bukti', $record->id))
                        ->openUrlInNewTab(),
                    Tables\Actions\Action::make('tandai_siap')
                        ->label('Tandai Siap Diambil')
                        ->icon('heroicon-o-check')
                        ->color('warning')
                        ->action(fn (Legalisir $record) => $record->update([
                            'status' => 'siap_diambil',
                            'tanggal_selesai' => now(),
                        ])),
                    Tables\Actions\Action::make('tandai_diambil')
                        ->label('Tandai Sudah Diambil')
                        ->icon('heroicon-o-hand-thumb-up')
                        ->color('primary')
                        ->form([
                            Forms\Components\TextInput::make('pengambil')
                                ->label('Nama Pengambil')
                                ->default(fn (Legalisir $record) => $record->nama_pemohon)
                                ->required(),
                        ])
                        ->action(function (Legalisir $record, array $data) {
                            $record->update([
                                'status' => 'diambil',
                                'pengambil' => $data['pengambil'],
                                'tanggal_diambil' => now(),
                            ]);
                        }),
                    Tables\Actions\DeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('export_csv')
                        ->label('Ekspor CSV')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->action(function ($records) {
                            $headers = [
                                'Content-Type' => 'text/csv',
                                'Content-Disposition' => 'attachment; filename="legalisir-' . date('Ymd-His') . '.csv"',
                            ];

                            $callback = function () use ($records) {
                                $handle = fopen('php://output', 'w');
                                fputcsv($handle, ['No Registrasi', 'Nama Pemohon', 'Kontak', 'Status Pemohon', 'Jenis Dokumen', 'Jumlah Lembar', 'Tgl Permohonan', 'Tgl Selesai', 'Tgl Diambil', 'Pengambil', 'Status']);
                                foreach ($records as $row) {
                                    fputcsv($handle, [
                                        $row->nomor_legalisir,
                                        $row->nama_pemohon,
                                        $row->kontak,
                                        $row->status_pemohon,
                                        $row->jenis_dokumen,
                                        $row->jumlah_lembar,
                                        $row->tanggal_permohonan?->format('Y-m-d'),
                                        $row->tanggal_selesai?->format('Y-m-d'),
                                        $row->tanggal_diambil?->format('Y-m-d'),
                                        $row->pengambil,
                                        $row->status,
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
            'index' => Pages\ListLegalisirs::route('/'),
            'create' => Pages\CreateLegalisir::route('/create'),
            'edit' => Pages\EditLegalisir::route('/{record}/edit'),
        ];
    }
}
