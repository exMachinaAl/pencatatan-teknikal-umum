<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\AuthorizesModuleAccess;
use App\Filament\Resources\PegawaiResource\Pages;
use App\Models\Pegawai;
use App\Models\RefGolongan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Response;

class PegawaiResource extends Resource
{
    use AuthorizesModuleAccess;

    protected static ?string $model = Pegawai::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Administrasi Kepegawaian';

    protected static ?string $modelLabel = 'Pegawai';

    protected static ?string $pluralModelLabel = 'Data Pegawai';

    protected static ?int $navigationSort = 1;

    protected static function accessModule(): string
    {
        return 'kepegawaian';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Data Inti Pegawai')
                    ->description('Isi identitas utama yang diperlukan untuk administrasi kepegawaian.')
                    ->schema([
                        Forms\Components\TextInput::make('nama')
                            ->label('Nama Lengkap & Gelar')
                            ->required()
                            ->maxLength(150),

                        Forms\Components\TextInput::make('nip')
                            ->label('NIP')
                            ->helperText('Kosongkan untuk pegawai yang belum atau tidak memiliki NIP.')
                            ->maxLength(30)
                            ->unique(ignoreRecord: true),

                        Forms\Components\Select::make('status_kepegawaian')
                            ->label('Status Kepegawaian')
                            ->options([
                                'PNS' => 'PNS (Pegawai Negeri Sipil)',
                                'PPPK' => 'PPPK (Pegawai Pemerintah dengan Perjanjian Kerja)',
                                'Honorer' => 'Honorer / Tenaga Kerja Sukarela',
                            ])
                            ->default('PNS')
                            ->required(),

                        Forms\Components\Select::make('golongan_id')
                            ->label('Golongan / Ruang')
                            ->relationship('golongan', 'kode')
                            ->getOptionLabelFromRecordUsing(fn (RefGolongan $record) => "{$record->pangkat} ({$record->kode})")
                            ->searchable()
                            ->preload()
                            ->helperText('Boleh dikosongkan untuk pegawai yang tidak memiliki golongan.'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('mobile_summary')
                    ->label('Ringkasan')
                    ->state(fn (Pegawai $record): string => $record->nama)
                    ->description(fn (Pegawai $record): string => collect([
                        $record->nip ?: 'Non-NIP',
                        $record->status_kepegawaian,
                        $record->golongan?->kode ? "Gol. {$record->golongan->kode}" : null,
                    ])->filter()->implode(' · '))
                    ->weight('bold')
                    ->wrap()
                    ->extraAttributes(['class' => 'tu-mobile-summary'])
                    ->hiddenFrom('md'),

                Tables\Columns\TextColumn::make('nama')
                    ->label('Nama Pegawai')
                    ->searchable(['nama', 'nip'])
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (Pegawai $record) => $record->nip ?: 'Non-NIP')
                    ->visibleFrom('md'),

                Tables\Columns\BadgeColumn::make('status_kepegawaian')
                    ->label('Status')
                    ->colors([
                        'primary' => 'PNS',
                        'warning' => 'PPPK',
                        'gray' => 'Honorer',
                    ])
                    ->sortable()
                    ->visibleFrom('md'),

                Tables\Columns\TextColumn::make('golongan.kode')
                    ->label('Gol/Ruang')
                    ->badge()
                    ->color('info')
                    ->sortable()
                    ->visibleFrom('md'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status_kepegawaian')
                    ->label('Status Pegawai')
                    ->options([
                        'PNS' => 'PNS',
                        'PPPK' => 'PPPK',
                        'Honorer' => 'Honorer',
                    ]),

                Tables\Filters\SelectFilter::make('golongan_id')
                    ->label('Golongan')
                    ->relationship('golongan', 'kode'),

                Tables\Filters\Filter::make('calon_kp')
                    ->label('Calon Kenaikan Pangkat (<= 6 Bulan)')
                    ->query(fn (Builder $query) => $query->calonKp()),

                Tables\Filters\Filter::make('jatuh_tempo_kgb')
                    ->label('Jatuh Tempo KGB (<= 3 Bulan)')
                    ->query(fn (Builder $query) => $query->whereNotNull('tmt_kgb_terakhir')
                        ->where('tmt_kgb_terakhir', '<=', now()->subYears(2)->addMonths(3))),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status Keaktifan'),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\Action::make('buat_kp')
                        ->label('Ajukan Kenaikan Pangkat')
                        ->icon('heroicon-o-arrow-trending-up')
                        ->color('success')
                        ->authorize(fn (): bool => static::resourcePermission('create'))
                        ->url(fn (Pegawai $record) => route('filament.admin.resources.kenaikan-pangkats.create', ['pegawai_id' => $record->id])),
                    Tables\Actions\Action::make('buat_kgb')
                        ->label('Proses Gaji Berkala')
                        ->icon('heroicon-o-banknotes')
                        ->color('warning')
                        ->authorize(fn (): bool => static::resourcePermission('create'))
                        ->url(fn (Pegawai $record) => route('filament.admin.resources.gaji-berkalas.create', ['pegawai_id' => $record->id])),
                    Tables\Actions\DeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\BulkAction::make('export_csv')
                        ->label('Ekspor CSV')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->authorize(fn (): bool => static::resourcePermission('export'))
                        ->action(function ($records) {
                            static::authorizeResourceAction('export');
                            $headers = [
                                'Content-Type' => 'text/csv',
                                'Content-Disposition' => 'attachment; filename="data-pegawai-'.date('Ymd-His').'.csv"',
                            ];

                            $callback = function () use ($records) {
                                $handle = fopen('php://output', 'w');
                                fputcsv($handle, ['NIP', 'Nama Lengkap & Gelar', 'Status Kepegawaian', 'Golongan / Ruang']);
                                foreach ($records as $row) {
                                    fputcsv($handle, [
                                        $row->nip,
                                        $row->nama,
                                        $row->status_kepegawaian,
                                        $row->golongan?->kode,
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
            'index' => Pages\ListPegawais::route('/'),
            'create' => Pages\CreatePegawai::route('/create'),
            'edit' => Pages\EditPegawai::route('/{record}/edit'),
        ];
    }
}
