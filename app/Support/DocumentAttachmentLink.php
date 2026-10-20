<?php

namespace App\Support;

use App\Models\Lampiran;
use Illuminate\Database\Eloquent\Model;

class DocumentAttachmentLink
{
    /**
     * @return array<int, array{icon: string, label: string, url: string, title: string}>
     */
    public static function links(Model $record, ?string $externalUrl = null): array
    {
        $links = [];
        $attachment = self::currentAttachment($record);

        if ($attachment) {
            $links[] = [
                'icon' => $attachment->is_previewable ? 'heroicon-o-document-magnifying-glass' : 'heroicon-o-arrow-down-tray',
                'label' => $attachment->is_previewable ? 'Lihat file' : 'Unduh file',
                'url' => route($attachment->is_previewable ? 'lampiran.preview' : 'lampiran.download', $attachment),
                'title' => $attachment->nama_file,
            ];
        }

        if ($url = self::safeExternalUrl($externalUrl)) {
            $links[] = [
                'icon' => 'heroicon-o-arrow-top-right-on-square',
                'label' => self::externalLabel($url),
                'url' => $url,
                'title' => 'Buka lampiran eksternal di tab baru',
            ];
        }

        return $links;
    }

    private static function currentAttachment(Model $record): ?Lampiran
    {
        if (! method_exists($record, 'lampiran')) {
            return null;
        }

        $record->loadMissing('lampiran');

        return $record->getRelation('lampiran')
            ->where('is_current', true)
            ->sortByDesc('versi')
            ->first();
    }

    private static function safeExternalUrl(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https' ? $url : null;
    }

    private static function externalLabel(string $url): string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $path = strtolower((string) parse_url($url, PHP_URL_PATH));

        if ($host === 'docs.google.com' && str_starts_with($path, '/spreadsheets/')) {
            return 'Google Sheets';
        }

        if ($host === 'docs.google.com' && str_starts_with($path, '/forms/')) {
            return 'Google Form';
        }

        if ($host === 'drive.google.com' || str_ends_with($host, '.drive.google.com')) {
            return 'Google Drive';
        }

        return 'Buka tautan';
    }
}
