<?php

namespace App\Filament\Resources;

use App\Filament\Resources\KenaikanPangkatResource\Pages;
use App\Models\KenaikanPangkat;
use App\Models\Pegawai;
use App\Models\RefGolongan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;

class KenaikanPangkatResource extends Resource
{
    protected static ?string $model = KenaikanPangkat::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-trending-up';

    protected static ?string $navigationGroup = 'Administrasi Kepegawaian';

    protected static ?string $modelLabel = 'Kenaikan Pangkat';

    protected static ?string $pluralModelLabel = 'Kenaikan Pangkat (KP)';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Pegawai & Pengajuan')
                    ->schema([
                        Forms\Components\Select::make('pegawai_id')
                            ->label('Nama Pegawai')
                            ->relationship('pegawai', 'nama')
                            ->getOptionLabelFromRecordUsing(fn (Pegawai $record) => "{$record->nama} - " . ($record->nip ?: 'Non-NIP') . " ({$record->golongan?->kode})")
                            ->searchable()
                            ->preload()
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set) {
                                if ($state) {
                                    $pegawai = Pegawai::find($state);
                                    if ($pegawai && $pegawai->golongan_id) {
                                        $set('golongan_lama_id', $pegawai->golongan_id);
                                    }
                                }
                            }),

                        Forms\Components\Select::make('jenis')
                            ->label('Jenis Kenaikan Pangkat')
                            ->options(KenaikanPangkat::JENIS_KP)
                            ->default('reguler')
                            ->required(),

                        Forms\Components\Select::make('golongan_lama_id')
                            ->label('Golongan Lama')
                            ->relationship('golonganLama', 'kode')
                            ->getOptionLabelFromRecordUsing(fn (RefGolongan $record) => "{$record->pangkat} ({$record->kode})")
                            ->searchable()
                            ->preload(),

                        Forms\Components\Select::make('golongan_baru_id')
                            ->label('Golongan Baru (Tujuan)')
                            ->relationship('golonganBaru', 'kode')
                            ->getOptionLabelFromRecordUsing(fn (RefGolongan $record) => "{$record->pangkat} ({$record->kode})")
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\TextInput::make('periode')
                            ->label('Periode Pengajuan')
                            ->placeholder('Contoh: 2026-04 atau Periode April 2026')
                            ->default(fn () => date('Y-m')),

                        Forms\Components\DatePicker::make('tmt')
                            ->label('TMT Kenaikan Pangkat')
                            ->displayFormat('d/m/Y'),
                    ])->columns(2),

                Forms\Components\Section::make('Status & Dokumen SK')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->label('Status Alur Proses')
                            ->options(KenaikanPangkat::STATUSES)
                            ->default('berkas_disiapkan')
                            ->required(),

                        Forms\Components\TextInput::make('nomor_sk')
                            ->label('Nomor SK Kenaikan Pangkat')
                            ->placeholder('Diisi jika SK sudah terbit'),

                        Forms\Components\DatePicker::make('tanggal_sk')
                            ->label('Tanggal SK')
                            ->displayFormat('d/m/Y'),

                        Forms\Components\TextInput::make('pejabat_penetap')
                            ->label('Pejabat Penetap')
                            ->placeholder('Contoh: Gubernur, Kepala BKD, Kepala Disdik'),

                        Forms\Components\Textarea::make('catatan')
                            ->label('Catatan / Checklist Kelengkapan Berkas')
                            ->placeholder('Contoh: SKP 2 tahun terakhir lengkap, PAK sudah diverifikasi...')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('pegawai.nama')
                    ->label('Nama Pegawai')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (KenaikanPangkat $record) => $record->pegawai?->nip ?: '-'),

                Tables\Columns\BadgeColumn::make('jenis')
                    ->label('Jenis KP')
                    ->colors([
                        'primary' => 'reguler',
                        'success' => 'pilihan',
                        'warning' => 'penyesuaian_ijazah',
                    ])
                    ->formatStateUsing(fn ($state) => KenaikanPangkat::JENIS_KP[$state] ?? $state),

                Tables\Columns\TextColumn::make('golonganLama.kode')
                    ->label('Gol Lama')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('golonganBaru.kode')
                    ->label('Gol Baru')
                    ->badge()
                    ->color('success'),

                Tables\Columns\TextColumn::make('periode')
                    ->label('Periode')
                    ->sortable(),

                Tables\Columns\TextColumn::make('tmt')
                    ->label('TMT')
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status Alur')
                    ->colors([
                        'secondary' => 'berkas_disiapkan',
                        'info' => 'diajukan',
                        'success' => 'sk_terbit',
                        'primary' => 'diserahkan',
                    ])
                    ->formatStateUsing(fn ($state) => KenaikanPangkat::STATUSES[$state] ?? $state)
                    ->sortable(),

                Tables\Columns\TextColumn::make('nomor_sk')
                    ->label('Nomor SK')
                    ->searchable()
                    ->placeholder('Belum terbit')
                    ->limit(25),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status Proses')
                    ->options(KenaikanPangkat::STATUSES),

                Tables\Filters\SelectFilter::make('jenis')
                    ->label('Jenis KP')
                    ->options(KenaikanPangkat::JENIS_KP),

                Tables\Filters\SelectFilter::make('golongan_baru_id')
                    ->label('Golongan Baru')
                    ->relationship('golonganBaru', 'kode'),

                Tables\Filters\Filter::make('periode')
                    ->form([
                        Forms\Components\TextInput::make('periode_tahun')
                            ->label('Tahun / Periode')
                            ->placeholder('2026'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query->when($data['periode_tahun'], fn ($q, $t) => $q->where('periode', 'like', "%{$t}%"));
                    }),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\Action::make('update_status')
                        ->label('Ubah Status')
                        ->icon('heroicon-o-arrow-path')
                        ->form([
                            Forms\Components\Select::make('status')
                                ->label('Status Baru')
                                ->options(KenaikanPangkat::STATUSES)
                                ->required(),
                            Forms\Components\TextInput::make('nomor_sk')
                                ->label('Nomor SK (Jika Terbit)'),
                            Forms\Components\DatePicker::make('tanggal_sk')
                                ->label('Tanggal SK'),
                        ])
                        ->action(function (KenaikanPangkat $record, array $data) {
                            $record->update(array_filter($data));
                            // Jika SK terbit/diserahkan, otomatis update golongan pegawai!
                            if (in_array($data['status'], ['sk_terbit', 'diserahkan']) && $record->golongan_baru_id) {
                                $record->pegawai->update([
                                    'golongan_id' => $record->golongan_baru_id,
                                    'tmt_pangkat_terakhir' => $record->tmt ?: now(),
                                ]);
                            }
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
                                'Content-Disposition' => 'attachment; filename="kenaikan-pangkat-' . date('Ymd-His') . '.csv"',
                            ];

                            $callback = function () use ($records) {
                                $handle = fopen('php://output', 'w');
                                fputcsv($handle, ['NIP', 'Nama Pegawai', 'Jenis KP', 'Gol Lama', 'Gol Baru', 'Periode', 'TMT', 'Nomor SK', 'Tanggal SK', 'Status']);
                                foreach ($records as $row) {
                                    fputcsv($handle, [
                                        $row->pegawai?->nip,
                                        $row->pegawai?->nama,
                                        $row->jenis,
                                        $row->golonganLama?->kode,
                                        $row->golonganBaru?->kode,
                                        $row->periode,
                                        $row->tmt?->format('Y-m-d'),
                                        $row->nomor_sk,
                                        $row->tanggal_sk?->format('Y-m-d'),
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
            'index' => Pages\ListKenaikanPangkats::route('/'),
            'create' => Pages\CreateKenaikanPangkat::route('/create'),
            'edit' => Pages\EditKenaikanPangkat::route('/{record}/edit'),
        ];
    }
}
