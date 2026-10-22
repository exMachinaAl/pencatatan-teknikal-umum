<?php

namespace App\Filament\Pages;

use App\Filament\Resources\LegalisirResource;
use App\Filament\Resources\SuratKeluarResource;
use App\Filament\Resources\SuratMasukResource;
use App\Models\Legalisir;
use App\Models\SuratKeluar;
use App\Models\SuratMasuk;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class GlobalDocumentSearch extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-magnifying-glass';

    protected static ?string $navigationLabel = 'Pencarian Terpadu';

    protected static ?string $title = 'Pencarian Terpadu';

    protected static ?string $navigationGroup = 'Surat & Kearsipan';

    protected static ?int $navigationSort = 0;

    protected static string $view = 'filament.pages.global-document-search';

    public string $query = '';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->canPerform('surat', 'view') || $user?->canPerform('legalisir', 'view'));
    }

    public function getResults(): Collection
    {
        $term = trim($this->query);
        if (mb_strlen($term) < 2) {
            return collect();
        }

        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%';
        $results = collect();
        $user = auth()->user();

        if ($user?->canPerform('surat', 'view')) {
            SuratMasuk::query()
                ->with('klasifikasi')
                ->where(function ($query) use ($like) {
                    $query->where('nomor_agenda', 'like', $like)
                        ->orWhere('nomor_surat', 'like', $like)
                        ->orWhere('pengirim', 'like', $like)
                        ->orWhereHas('klasifikasi', fn ($classification) => $classification
                            ->where('kode', 'like', $like)
                            ->orWhere('nama', 'like', $like));
                })
                ->latest('tanggal_diterima')->limit(12)->get()
                ->each(fn (SuratMasuk $record) => $results->push([
                    'type' => 'Surat masuk',
                    'number' => $record->nomor_surat ?: $record->nomor_agenda,
                    'title' => $record->pengirim,
                    'meta' => collect([
                        $record->klasifikasi?->kode ? 'Klasifikasi '.$record->klasifikasi->kode : null,
                        $record->tanggal_diterima?->format('d/m/Y'),
                    ])->filter()->implode(' · '),
                    'status' => SuratMasuk::STATUSES[$record->status] ?? $record->status,
                    'url' => SuratMasukResource::getUrl('view', ['record' => $record]),
                ]));

            SuratKeluar::query()
                ->with('klasifikasi')
                ->where(function ($query) use ($like) {
                    $query->where('nomor_surat', 'like', $like)
                        ->orWhere('tujuan', 'like', $like)
                        ->orWhereHas('klasifikasi', fn ($classification) => $classification
                            ->where('kode', 'like', $like)
                            ->orWhere('nama', 'like', $like));
                })
                ->latest('tanggal_surat')->limit(12)->get()
                ->each(fn (SuratKeluar $record) => $results->push([
                    'type' => 'Surat keluar',
                    'number' => $record->nomor_surat,
                    'title' => $record->tujuan,
                    'meta' => collect([
                        $record->klasifikasi?->kode ? 'Klasifikasi '.$record->klasifikasi->kode : null,
                        $record->tanggal_surat?->format('d/m/Y'),
                    ])->filter()->implode(' · '),
                    'status' => SuratKeluar::STATUSES[$record->status] ?? $record->status,
                    'url' => SuratKeluarResource::getUrl('view', ['record' => $record]),
                ]));
        }

        if ($user?->canPerform('legalisir', 'view')) {
            Legalisir::query()
                ->where(function ($query) use ($like) {
                    $query->where('nomor_legalisir', 'like', $like)
                        ->orWhere('nomor_antrean', 'like', $like)
                        ->orWhere('nama_pemohon', 'like', $like)
                        ->orWhere('jenis_dokumen', 'like', $like)
                        ->orWhere('lokasi_arsip_fisik', 'like', $like);
                })
                ->latest('tanggal_permohonan')->limit(12)->get()
                ->each(fn (Legalisir $record) => $results->push([
                    'type' => 'Legalisir',
                    'number' => $record->nomor_antrean ?: $record->nomor_legalisir,
                    'title' => $record->nama_pemohon,
                    'meta' => collect([
                        $record->jenis_dokumen,
                        $record->lokasi_arsip_fisik,
                        $record->tanggal_permohonan?->format('d/m/Y'),
                    ])->filter()->implode(' · '),
                    'status' => Legalisir::STATUSES[$record->status] ?? $record->status,
                    'url' => LegalisirResource::getUrl('view', ['record' => $record]),
                ]));
        }

        return $results->sortByDesc('number')->take(30)->values();
    }
}
