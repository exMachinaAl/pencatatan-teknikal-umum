<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SuratMasukResource\Pages;
use App\Models\Disposisi;
use App\Models\RefKlasifikasiSurat;
use App\Models\SuratMasuk;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;

class SuratMasukResource extends Resource
{
    protected static ?string $model = SuratMasuk::class;

    protected static ?string $navigationIcon = 'heroicon-o-inbox-arrow-down';

    protected static ?string $navigationGroup = 'Surat & Kearsipan';

    protected static ?string $modelLabel = 'Surat Masuk';

    protected static ?string $pluralModelLabel = 'Surat Masuk';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Registrasi & Agenda Surat')
                    ->schema([
                        Forms\Components\TextInput::make('nomor_agenda')
                            ->label('Nomor Agenda')
                            ->default(fn () => SuratMasuk::generateNomorAgenda())
                            ->required()
                            ->unique(ignoreRecord: true),

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

                        Forms\Components\Textarea::make('perihal')
                            ->label('Perihal / Ringkasan Isi Surat')
                            ->required()
                            ->rows(3)
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('Klasifikasi & Arsip Digital')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Forms\Components\Select::make('klasifikasi_id')
                            ->label('Kode Klasifikasi Surat')
                            ->relationship('klasifikasi', 'kode')
                            ->getOptionLabelFromRecordUsing(fn (RefKlasifikasiSurat $record) => "{$record->kode} - {$record->nama}")
                            ->searchable()
                            ->preload(),

                        Forms\Components\Select::make('sifat')
                            ->label('Sifat Surat')
                            ->options(SuratMasuk::SIFAT)
                            ->default('biasa')
                            ->required(),

                        Forms\Components\Select::make('status')
                            ->label('Status Surat')
                            ->options(SuratMasuk::STATUSES)
                            ->default('baru')
                            ->required(),

                        Forms\Components\TextInput::make('lokasi_arsip_fisik')
                            ->label('Lokasi Arsip Fisik')
                            ->placeholder('Contoh: Lemari A, Rak 2, Ordner 2026'),

                        Forms\Components\FileUpload::make('file_path')
                            ->label('Softfile Scan Surat (PDF / JPG / PNG)')
                            ->disk('local')
                            ->directory('arsip_surat_masuk')
                            ->visibility('private')
                            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                            ->maxSize(5120) // Maksimal 5 MB
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('tautan_gform')
                            ->label('Tautan Google Drive / Google Form')
                            ->placeholder('https://drive.google.com/...')
                            ->url()
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('Disposisi Pimpinan (Kepala Sekolah / TU)')
                    ->description('Lembar disposisi tindak lanjut surat')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Forms\Components\Repeater::make('disposisi')
                            ->relationship()
                            ->schema([
                                Forms\Components\TextInput::make('kepada')
                                    ->label('Diteruskan Kepada')
                                    ->placeholder('Contoh: Wakasek Kurikulum, Guru Pembina OSIS')
                                    ->required(),

                                Forms\Components\DatePicker::make('batas_waktu')
                                    ->label('Batas Waktu Selesai')
                                    ->displayFormat('d/m/Y'),

                                Forms\Components\Textarea::make('instruksi')
                                    ->label('Instruksi / Catatan Disposisi')
                                    ->placeholder('Contoh: Mohon koordinasikan dan buat laporan tindak lanjut.')
                                    ->rows(2)
                                    ->columnSpanFull(),

                                Forms\Components\Select::make('status')
                                    ->label('Status Tindak Lanjut')
                                    ->options(Disposisi::STATUSES)
                                    ->default('terbuka'),

                                Forms\Components\Textarea::make('catatan_tindak_lanjut')
                                    ->label('Hasil / Laporan Tindak Lanjut')
                                    ->rows(2)
                                    ->columnSpanFull(),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nomor_agenda')
                    ->label('No. Agenda')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('perihal')
                    ->label('Perihal')
                    ->searchable()
                    ->limit(48)
                    ->wrap()
                    ->description(fn (SuratMasuk $record) => $record->nomor_surat ? "No. surat: {$record->nomor_surat}" : null),

                Tables\Columns\TextColumn::make('pengirim')
                    ->label('Pengirim')
                    ->searchable()
                    ->weight('semibold')
                    ->limit(28)
                    ->wrap()
                    ->description(fn (SuratMasuk $record) => $record->tanggal_diterima?->translatedFormat('d M Y')),

                Tables\Columns\BadgeColumn::make('sifat')
                    ->label('Sifat')
                    ->colors([
                        'gray' => 'biasa',
                        'warning' => 'penting',
                        'danger' => 'segera',
                        'primary' => 'rahasia',
                    ])
                    ->formatStateUsing(fn ($state) => SuratMasuk::SIFAT[$state] ?? $state),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'info' => 'baru',
                        'warning' => 'didisposisi',
                        'success' => 'selesai',
                        'gray' => 'diarsipkan',
                    ])
                    ->formatStateUsing(fn ($state) => SuratMasuk::STATUSES[$state] ?? $state)
                    ->sortable(),

                Tables\Columns\IconColumn::make('file_path')
                    ->label('Softfile')
                    ->boolean()
                    ->trueIcon('heroicon-o-paper-clip')
                    ->falseIcon('heroicon-o-minus')
                    ->color(fn ($state) => $state ? 'success' : 'gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('lokasi_arsip_fisik')
                    ->label('Lokasi Fisik')
                    ->limit(20)
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('tanggal_diterima', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status Surat')
                    ->options(SuratMasuk::STATUSES),

                Tables\Filters\SelectFilter::make('sifat')
                    ->label('Sifat Surat')
                    ->options(SuratMasuk::SIFAT),

                Tables\Filters\SelectFilter::make('klasifikasi_id')
                    ->label('Klasifikasi')
                    ->relationship('klasifikasi', 'kode'),

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
                    Tables\Actions\Action::make('arsipkan')
                        ->label('Tandai Diarsipkan')
                        ->icon('heroicon-o-archive-box')
                        ->color('gray')
                        ->form([
                            Forms\Components\TextInput::make('lokasi_arsip_fisik')
                                ->label('Lokasi Fisik Arsip')
                                ->default(fn (SuratMasuk $record) => $record->lokasi_arsip_fisik)
                                ->required(),
                        ])
                        ->action(function (SuratMasuk $record, array $data) {
                            $record->update([
                                'status' => 'diarsipkan',
                                'lokasi_arsip_fisik' => $data['lokasi_arsip_fisik'],
                                'diarsipkan_pada' => now(),
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
                                'Content-Disposition' => 'attachment; filename="surat-masuk-' . date('Ymd-His') . '.csv"',
                            ];

                            $callback = function () use ($records) {
                                $handle = fopen('php://output', 'w');
                                fputcsv($handle, ['No Agenda', 'No Surat Asal', 'Tgl Surat', 'Tgl Diterima', 'Pengirim', 'Perihal', 'Sifat', 'Status', 'Lokasi Arsip']);
                                foreach ($records as $row) {
                                    fputcsv($handle, [
                                        $row->nomor_agenda,
                                        $row->nomor_surat,
                                        $row->tanggal_surat?->format('Y-m-d'),
                                        $row->tanggal_diterima?->format('Y-m-d'),
                                        $row->pengirim,
                                        $row->perihal,
                                        $row->sifat,
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
            'index' => Pages\ListSuratMasuks::route('/'),
            'create' => Pages\CreateSuratMasuk::route('/create'),
            'view' => Pages\ViewSuratMasuk::route('/{record}'),
            'edit' => Pages\EditSuratMasuk::route('/{record}/edit'),
        ];
    }
}
