<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GajiBerkalaResource\Pages;
use App\Models\GajiBerkala;
use App\Models\Pegawai;
use App\Models\RefGolongan;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;

class GajiBerkalaResource extends Resource
{
    protected static ?string $model = GajiBerkala::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Administrasi Kepegawaian';

    protected static ?string $modelLabel = 'Gaji Berkala';

    protected static ?string $pluralModelLabel = 'Kenaikan Gaji Berkala (KGB)';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        $canViewSalary = Auth::user()?->canViewSalary() ?? false;

        return $form
            ->schema([
                Forms\Components\Section::make('Pegawai & TMT')
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
                                        $set('golongan_id', $pegawai->golongan_id);
                                    }
                                }
                            }),

                        Forms\Components\Select::make('golongan_id')
                            ->label('Golongan / Ruang')
                            ->relationship('golongan', 'kode')
                            ->getOptionLabelFromRecordUsing(fn (RefGolongan $record) => "{$record->pangkat} ({$record->kode})")
                            ->searchable()
                            ->preload(),

                        Forms\Components\DatePicker::make('tmt')
                            ->label('TMT KGB')
                            ->required()
                            ->displayFormat('d/m/Y')
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set) {
                                if ($state) {
                                    $next = Carbon::parse($state)->addYears(2)->format('Y-m-d');
                                    $set('tmt_berikutnya', $next);
                                }
                            }),

                        Forms\Components\DatePicker::make('tmt_berikutnya')
                            ->label('TMT KGB Berikutnya')
                            ->helperText('Otomatis +2 tahun, dapat disesuaikan bila perlu')
                            ->displayFormat('d/m/Y'),
                    ])->columns(2),

                Forms\Components\Section::make('Masa Kerja & Besaran Gaji')
                    ->description($canViewSalary ? 'Informasi nominal gaji pokok' : 'Data gaji dibatasi (Hanya Admin / Kepala TU)')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Forms\Components\TextInput::make('mk_tahun')
                            ->label('Masa Kerja Golongan (Tahun)')
                            ->numeric()
                            ->default(0)
                            ->required(),

                        Forms\Components\TextInput::make('mk_bulan')
                            ->label('Masa Kerja Golongan (Bulan)')
                            ->numeric()
                            ->default(0)
                            ->required(),

                        Forms\Components\TextInput::make('gaji_pokok_lama')
                            ->label('Gaji Pokok Lama (Rp)')
                            ->numeric()
                            ->prefix('Rp')
                            ->visible($canViewSalary),

                        Forms\Components\TextInput::make('gaji_pokok_baru')
                            ->label('Gaji Pokok Baru (Rp)')
                            ->numeric()
                            ->prefix('Rp')
                            ->visible($canViewSalary),
                    ])->columns(2),

                Forms\Components\Section::make('Surat Keputusan & Pejabat')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Forms\Components\TextInput::make('nomor_sk')
                            ->label('Nomor SK / Surat Pemberitahuan'),

                        Forms\Components\DatePicker::make('tanggal_sk')
                            ->label('Tanggal SK')
                            ->displayFormat('d/m/Y'),

                        Forms\Components\TextInput::make('pejabat_penetap')
                            ->label('Pejabat Penetap')
                            ->placeholder('Contoh: Kepala Dinas Pendidikan Provinsi'),

                        Forms\Components\Select::make('status')
                            ->label('Status Proses')
                            ->options(GajiBerkala::STATUSES)
                            ->default('diproses')
                            ->required(),

                        Forms\Components\Textarea::make('catatan')
                            ->label('Catatan Tambahan')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        $canViewSalary = Auth::user()?->canViewSalary() ?? false;

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('pegawai.nama')
                    ->label('Nama Pegawai')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (GajiBerkala $record) => $record->pegawai?->nip ?: '-'),

                Tables\Columns\TextColumn::make('golongan.kode')
                    ->label('Gol/Ruang')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('tmt')
                    ->label('TMT KGB')
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('tmt_berikutnya')
                    ->label('TMT Berikutnya')
                    ->date('d M Y')
                    ->badge()
                    ->color(function (GajiBerkala $record) {
                        if (! $record->tmt_berikutnya) return 'gray';
                        $diff = now()->diffInMonths($record->tmt_berikutnya, false);
                        return ($diff <= 3 && $diff >= -1) ? 'danger' : 'success';
                    })
                    ->description(function (GajiBerkala $record) {
                        if (! $record->tmt_berikutnya) return null;
                        if ($record->tmt_berikutnya->lte(now()->addMonths(3))) {
                            return '⚡ Jatuh Tempo';
                        }
                        return null;
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('masa_kerja')
                    ->label('Masa Kerja')
                    ->state(fn (GajiBerkala $record) => "{$record->mk_tahun} Thn {$record->mk_bulan} Bln"),

                Tables\Columns\TextColumn::make('gaji_pokok_baru')
                    ->label('Gaji Pokok Baru')
                    ->state(function (GajiBerkala $record) use ($canViewSalary) {
                        if (! $canViewSalary) {
                            return '•••••••• (Terselubung)';
                        }
                        return $record->gaji_pokok_baru ? 'Rp ' . number_format($record->gaji_pokok_baru, 0, ',', '.') : '-';
                    })
                    ->color($canViewSalary ? 'success' : 'gray'),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'warning' => 'diproses',
                        'info' => 'sk_terbit',
                        'success' => 'diserahkan',
                    ])
                    ->formatStateUsing(fn ($state) => GajiBerkala::STATUSES[$state] ?? $state),

                Tables\Columns\TextColumn::make('nomor_sk')
                    ->label('Nomor SK')
                    ->searchable()
                    ->placeholder('-')
                    ->limit(20),
            ])
            ->filters([
                Tables\Filters\Filter::make('perlu_tindak_lanjut')
                    ->label('Jatuh tempo dan belum diserahkan')
                    ->query(fn (Builder $query) => $query->perluTindakLanjut()),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(GajiBerkala::STATUSES),

                Tables\Filters\Filter::make('jatuh_tempo_3_bulan')
                    ->label('⏳ Jatuh Tempo Dalam 3 Bulan Ke Depan')
                    ->query(fn (Builder $query) => $query->jatuhTempo()),

                Tables\Filters\SelectFilter::make('golongan_id')
                    ->label('Golongan')
                    ->relationship('golongan', 'kode'),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\Action::make('tandai_diserahkan')
                        ->label('Tandai Diserahkan')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (GajiBerkala $record) {
                            $record->update(['status' => 'diserahkan']);
                            // Update TMT KGB terakhir di master Pegawai
                            $record->pegawai->update([
                                'tmt_kgb_terakhir' => $record->tmt,
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
                        ->action(function ($records) use ($canViewSalary) {
                            $headers = [
                                'Content-Type' => 'text/csv',
                                'Content-Disposition' => 'attachment; filename="gaji-berkala-' . date('Ymd-His') . '.csv"',
                            ];

                            $callback = function () use ($records, $canViewSalary) {
                                $handle = fopen('php://output', 'w');
                                fputcsv($handle, ['NIP', 'Nama Pegawai', 'Golongan', 'TMT KGB', 'TMT Berikutnya', 'Masa Kerja Thn', 'Masa Kerja Bln', 'Gaji Pokok Baru', 'Nomor SK', 'Status']);
                                foreach ($records as $row) {
                                    fputcsv($handle, [
                                        $row->pegawai?->nip,
                                        $row->pegawai?->nama,
                                        $row->golongan?->kode,
                                        $row->tmt?->format('Y-m-d'),
                                        $row->tmt_berikutnya?->format('Y-m-d'),
                                        $row->mk_tahun,
                                        $row->mk_bulan,
                                        $canViewSalary ? $row->gaji_pokok_baru : '[TERSELUBUNG]',
                                        $row->nomor_sk,
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
            'index' => Pages\ListGajiBerkalas::route('/'),
            'create' => Pages\CreateGajiBerkala::route('/create'),
            'edit' => Pages\EditGajiBerkala::route('/{record}/edit'),
        ];
    }
}
