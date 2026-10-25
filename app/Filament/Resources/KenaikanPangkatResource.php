<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\AuthorizesModuleAccess;
use App\Filament\Resources\KenaikanPangkatResource\Pages;
use App\Models\AuditLog;
use App\Models\KenaikanPangkat;
use App\Models\Pegawai;
use App\Models\RefGolongan;
use App\Support\ConcurrentRecordUpdate;
use App\Support\SecureCsv;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Response;

class KenaikanPangkatResource extends Resource
{
    use AuthorizesModuleAccess;

    protected static ?string $model = KenaikanPangkat::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-trending-up';

    protected static ?string $navigationGroup = 'Administrasi Kepegawaian';

    protected static ?string $modelLabel = 'Kenaikan Pangkat';

    protected static ?string $pluralModelLabel = 'Kenaikan Pangkat (KP)';

    protected static ?int $navigationSort = 2;

    protected static function accessModule(): string
    {
        return 'kepegawaian';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Pegawai & Pengajuan')
                    ->schema([
                        Forms\Components\Select::make('pegawai_id')
                            ->label('Pegawai yang Mengajukan')
                            ->relationship('pegawai', 'nama')
                            ->getOptionLabelFromRecordUsing(fn (Pegawai $record) => "{$record->nama} - ".($record->nip ?: 'Non-NIP')." ({$record->golongan?->kode})")
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set): void {
                                if ($state) {
                                    $set('golongan_lama_id', Pegawai::find($state)?->golongan_id);
                                }
                            })
                            ->required(),

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

                        Forms\Components\Select::make('mk_tahun')
                            ->label('Masa Kerja (Tahun)')
                            ->options(array_combine(
                                range(0, 60),
                                array_map(fn (int $year): string => "{$year} tahun", range(0, 60)),
                            ))
                            ->default(0)
                            ->required()
                            ->native(false),

                        Forms\Components\Select::make('mk_bulan')
                            ->label('Masa Kerja (Bulan)')
                            ->options(array_combine(
                                range(0, 11),
                                array_map(fn (int $month): string => "{$month} bulan", range(0, 11)),
                            ))
                            ->default(0)
                            ->required()
                            ->native(false),

                        Forms\Components\TextInput::make('tautan_lampiran')
                            ->label('Tautan Lampiran / Google Sheets (Opsional)')
                            ->placeholder('https://drive.google.com/... atau https://docs.google.com/spreadsheets/...')
                            ->url()
                            ->rules(['nullable', 'starts_with:https://'])
                            ->maxLength(500)
                            ->helperText('Isi jika berkas berada di Google Drive, Google Sheets, atau layanan HTTPS lain.')
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
                    ->view('filament.tables.columns.document-mobile-summary', fn (KenaikanPangkat $record): array => [
                        'title' => $record->pegawai?->nama ?: 'Pegawai tidak tersedia',
                        'meta' => collect([
                            $record->pegawai?->nip ?: 'Non-NIP',
                            KenaikanPangkat::jenisLabel($record->jenis),
                            collect([$record->golonganLama?->kode, $record->golonganBaru?->kode])->filter()->implode(' → '),
                            "Masa kerja {$record->mk_tahun} thn {$record->mk_bulan} bln",
                            KenaikanPangkat::STATUSES[$record->status] ?? $record->status,
                        ])->filter()->implode(' · '),
                        'externalAttribute' => 'tautan_lampiran',
                    ])
                    ->extraCellAttributes(['class' => 'tu-mobile-summary-cell'])
                    ->hiddenFrom('md'),

                Tables\Columns\TextColumn::make('pegawai.nama')
                    ->label('Nama Pegawai')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (KenaikanPangkat $record) => $record->pegawai?->nip ?: '-')
                    ->visibleFrom('md'),

                Tables\Columns\BadgeColumn::make('jenis')
                    ->label('Jenis KP')
                    ->colors([
                        'primary' => 'reguler',
                        'success' => 'pilihan',
                        'warning' => 'penyesuaian_ijazah',
                    ])
                    ->formatStateUsing(fn ($state) => KenaikanPangkat::jenisLabel($state))
                    ->visibleFrom('md'),

                Tables\Columns\TextColumn::make('golonganLama.kode')
                    ->label('Gol Lama')
                    ->badge()
                    ->color('gray')
                    ->visibleFrom('md'),

                Tables\Columns\TextColumn::make('golonganBaru.kode')
                    ->label('Gol Baru')
                    ->badge()
                    ->color('success')
                    ->visibleFrom('md'),

                Tables\Columns\TextColumn::make('masa_kerja')
                    ->label('Masa Kerja')
                    ->state(fn (KenaikanPangkat $record): string => "{$record->mk_tahun} Thn {$record->mk_bulan} Bln")
                    ->visibleFrom('md'),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status Alur')
                    ->colors([
                        'secondary' => 'berkas_disiapkan',
                        'info' => 'diajukan',
                        'success' => 'sk_terbit',
                        'primary' => 'diserahkan',
                    ])
                    ->formatStateUsing(fn ($state) => KenaikanPangkat::STATUSES[$state] ?? $state)
                    ->sortable()
                    ->visibleFrom('md'),

                Tables\Columns\ViewColumn::make('document_links')
                    ->label('Lampiran')
                    ->view('filament.tables.columns.document-links', ['externalAttribute' => 'tautan_lampiran'])
                    ->extraCellAttributes(['class' => 'tu-document-cell'])
                    ->visibleFrom('md'),

            ])
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('lampiran'))
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

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\Action::make('update_status')
                        ->label('Ubah Status')
                        ->icon('heroicon-o-arrow-path')
                        ->authorize(fn (): bool => static::resourcePermission('transition'))
                        ->visible(fn (KenaikanPangkat $record): bool => $record->status !== 'diserahkan')
                        ->form([
                            Forms\Components\Select::make('status')
                                ->label('Status Baru')
                                ->options(KenaikanPangkat::STATUSES)
                                ->required(),
                        ])
                        ->action(function (KenaikanPangkat $record, array $data) {
                            static::authorizeResourceAction('transition');
                            ConcurrentRecordUpdate::run($record, function (KenaikanPangkat $locked) use ($data): void {
                                Pegawai::whereKey($locked->pegawai_id)->lockForUpdate()->firstOrFail();
                                $locked->update(array_filter($data, fn ($value) => $value !== null && $value !== ''));
                            });
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
                            AuditLog::record('export', 'KenaikanPangkat', null, null, ['jumlah_record' => $records->count()]);
                            $headers = [
                                'Content-Type' => 'text/csv',
                                'Content-Disposition' => 'attachment; filename="kenaikan-pangkat-'.date('Ymd-His').'.csv"',
                            ];

                            $callback = function () use ($records) {
                                $handle = fopen('php://output', 'w');
                                SecureCsv::writeRow($handle, ['NIP', 'Nama Pegawai', 'Jenis KP', 'Gol Lama', 'Gol Baru', 'Masa Kerja Tahun', 'Masa Kerja Bulan', 'Tautan Lampiran', 'Status']);
                                foreach ($records as $row) {
                                    SecureCsv::writeRow($handle, [
                                        $row->pegawai?->nip,
                                        $row->pegawai?->nama,
                                        $row->jenis,
                                        $row->golonganLama?->kode,
                                        $row->golonganBaru?->kode,
                                        $row->mk_tahun,
                                        $row->mk_bulan,
                                        $row->tautan_lampiran,
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
