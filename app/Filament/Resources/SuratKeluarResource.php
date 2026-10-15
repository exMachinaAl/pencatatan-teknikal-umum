<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SuratKeluarResource\Pages;
use App\Models\RefKlasifikasiSurat;
use App\Models\SuratKeluar;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Response;

class SuratKeluarResource extends Resource
{
    protected static ?string $model = SuratKeluar::class;

    protected static ?string $navigationIcon = 'heroicon-o-paper-airplane';

    protected static ?string $navigationGroup = 'Surat & Kearsipan';

    protected static ?string $modelLabel = 'Surat Keluar';

    protected static ?string $pluralModelLabel = 'Surat Keluar';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Penomoran & Tanggal Surat')
                    ->schema([
                        Forms\Components\TextInput::make('nomor_surat')
                            ->label('Nomor Surat Keluar')
                            ->helperText('Dihasilkan otomatis saat pembuatan baru sesuai urutan tahun')
                            ->default(function () {
                                $data = SuratKeluar::generateNomorSurat();
                                return $data['nomor_surat'];
                            })
                            ->required()
                            ->unique(ignoreRecord: true),

                        Forms\Components\DatePicker::make('tanggal_surat')
                            ->label('Tanggal Surat')
                            ->default(now())
                            ->required()
                            ->displayFormat('d/m/Y'),

                        Forms\Components\Hidden::make('nomor_urut')
                            ->default(function () {
                                $data = SuratKeluar::generateNomorSurat();
                                return $data['nomor_urut'];
                            }),

                        Forms\Components\Hidden::make('tahun')
                            ->default(fn () => (int) date('Y')),

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
                            ->label('Tautan Google Drive / Google Form')
                            ->placeholder('https://drive.google.com/...')
                            ->url()
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nomor_surat')
                    ->label('Nomor Surat')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('perihal')
                    ->label('Perihal')
                    ->searchable()
                    ->limit(48)
                    ->wrap(),

                Tables\Columns\TextColumn::make('tujuan')
                    ->label('Tujuan')
                    ->searchable()
                    ->weight('semibold')
                    ->limit(28)
                    ->wrap()
                    ->description(fn (SuratKeluar $record) => $record->tanggal_surat?->translatedFormat('d M Y')),

                Tables\Columns\BadgeColumn::make('sifat')
                    ->label('Sifat')
                    ->colors([
                        'gray' => 'biasa',
                        'warning' => 'penting',
                        'danger' => 'segera',
                        'primary' => 'rahasia',
                    ])
                    ->formatStateUsing(fn ($state) => SuratKeluar::SIFAT[$state] ?? $state),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'warning' => 'draf',
                        'info' => 'terkirim',
                        'gray' => 'diarsipkan',
                    ])
                    ->formatStateUsing(fn ($state) => SuratKeluar::STATUSES[$state] ?? $state)
                    ->sortable(),

                Tables\Columns\IconColumn::make('file_final_path')
                    ->label('File Final')
                    ->boolean()
                    ->trueIcon('heroicon-o-document-check')
                    ->falseIcon('heroicon-o-minus')
                    ->color(fn ($state) => $state ? 'success' : 'gray')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
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
                        ->action(fn (SuratKeluar $record) => $record->update(['status' => 'terkirim'])),
                    Tables\Actions\Action::make('arsipkan')
                        ->label('Tandai Diarsipkan')
                        ->icon('heroicon-o-archive-box')
                        ->color('gray')
                        ->form([
                            Forms\Components\TextInput::make('lokasi_arsip_fisik')
                                ->label('Lokasi Arsip Fisik')
                                ->default(fn (SuratKeluar $record) => $record->lokasi_arsip_fisik)
                                ->required(),
                        ])
                        ->action(function (SuratKeluar $record, array $data) {
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
                                'Content-Disposition' => 'attachment; filename="surat-keluar-' . date('Ymd-His') . '.csv"',
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
