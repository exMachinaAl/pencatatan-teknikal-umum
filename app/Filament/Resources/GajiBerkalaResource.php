<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\AuthorizesModuleAccess;
use App\Filament\Resources\GajiBerkalaResource\Pages;
use App\Models\GajiBerkala;
use App\Models\Pegawai;
use App\Models\RefGolongan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Response;

class GajiBerkalaResource extends Resource
{
    use AuthorizesModuleAccess;

    protected static ?string $model = GajiBerkala::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Administrasi Kepegawaian';

    protected static ?string $modelLabel = 'Gaji Berkala';

    protected static ?string $pluralModelLabel = 'Kenaikan Gaji Berkala (KGB)';

    protected static ?int $navigationSort = 3;

    protected static function accessModule(): string
    {
        return 'kepegawaian';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Data Gaji Berkala')
                    ->schema([
                        Forms\Components\Select::make('pegawai_id')
                            ->label('Nama Pegawai')
                            ->relationship('pegawai', 'nama')
                            ->getOptionLabelFromRecordUsing(fn (Pegawai $record) => "{$record->nama} - ".($record->nip ?: 'Non-NIP')." ({$record->golongan?->kode})")
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set): void {
                                if ($state) {
                                    $set('golongan_id', Pegawai::find($state)?->golongan_id);
                                }
                            })
                            ->required(),

                        Forms\Components\Select::make('golongan_id')
                            ->label('Golongan / Ruang')
                            ->relationship('golongan', 'kode')
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
                    ->view('filament.tables.columns.document-mobile-summary', fn (GajiBerkala $record): array => [
                        'title' => $record->pegawai?->nama ?: 'Pegawai tidak tersedia',
                        'meta' => collect([
                            $record->pegawai?->nip ?: 'Non-NIP',
                            $record->golongan?->kode ? "Gol. {$record->golongan->kode}" : null,
                            "Masa kerja {$record->mk_tahun} thn {$record->mk_bulan} bln",
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
                    ->description(fn (GajiBerkala $record) => $record->pegawai?->nip ?: '-')
                    ->visibleFrom('md'),

                Tables\Columns\TextColumn::make('golongan.kode')
                    ->label('Gol/Ruang')
                    ->badge()
                    ->color('info')
                    ->visibleFrom('md'),

                Tables\Columns\TextColumn::make('masa_kerja')
                    ->label('Masa Kerja')
                    ->state(fn (GajiBerkala $record) => "{$record->mk_tahun} Thn {$record->mk_bulan} Bln")
                    ->visibleFrom('md'),

                Tables\Columns\ViewColumn::make('document_links')
                    ->label('Lampiran')
                    ->view('filament.tables.columns.document-links', ['externalAttribute' => 'tautan_lampiran'])
                    ->extraCellAttributes(['class' => 'tu-document-cell'])
                    ->visibleFrom('md'),
            ])
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('lampiran'))
            ->filters([
                Tables\Filters\Filter::make('perlu_tindak_lanjut')
                    ->label('Jatuh tempo dan belum diserahkan')
                    ->query(fn (Builder $query) => $query->perluTindakLanjut()),

                Tables\Filters\SelectFilter::make('golongan_id')
                    ->label('Golongan')
                    ->relationship('golongan', 'kode'),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
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
                            $headers = [
                                'Content-Type' => 'text/csv',
                                'Content-Disposition' => 'attachment; filename="gaji-berkala-'.date('Ymd-His').'.csv"',
                            ];

                            $callback = function () use ($records) {
                                $handle = fopen('php://output', 'w');
                                fputcsv($handle, ['NIP', 'Nama Pegawai', 'Golongan', 'Masa Kerja Tahun', 'Masa Kerja Bulan', 'Tautan Lampiran']);
                                foreach ($records as $row) {
                                    fputcsv($handle, [
                                        $row->pegawai?->nip,
                                        $row->pegawai?->nama,
                                        $row->golongan?->kode,
                                        $row->mk_tahun,
                                        $row->mk_bulan,
                                        $row->tautan_lampiran,
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
            'index' => Pages\ListGajiBerkalas::route('/'),
            'create' => Pages\CreateGajiBerkala::route('/create'),
            'edit' => Pages\EditGajiBerkala::route('/{record}/edit'),
        ];
    }
}
