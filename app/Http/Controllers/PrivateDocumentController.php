<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Lampiran;
use App\Models\LampiranAccessLog;
use App\Models\Legalisir;
use App\Models\Pegawai;
use App\Models\SuratKeluar;
use App\Models\SuratMasuk;
use App\Models\User;
use App\Support\SecureDocumentPath;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrivateDocumentController extends Controller
{
    public function download(Lampiran $lampiran): StreamedResponse
    {
        $this->authorizeLampiran($lampiran);
        abort_unless(Storage::disk('local')->exists($lampiran->path), 404);

        $this->recordAccess($lampiran, 'download');

        return Storage::disk('local')->download($lampiran->path, $lampiran->nama_file);
    }

    public function preview(Lampiran $lampiran): BinaryFileResponse
    {
        $this->authorizeLampiran($lampiran);
        $disk = Storage::disk('local');
        abort_unless($lampiran->is_previewable && $disk->exists($lampiran->path), 404);

        $mime = strtolower((string) $disk->mimeType($lampiran->path));
        abort_unless(in_array($mime, ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'], true), 415);

        $this->recordAccess($lampiran, 'preview');

        return response()->file($disk->path($lampiran->path), [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'; sandbox",
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    public function fotoPegawai(Pegawai $pegawai): BinaryFileResponse
    {
        abort_unless(auth()->user()?->canPerform('kepegawaian', 'view'), 403);

        return $this->privateImage((string) $pegawai->foto_path);
    }

    public function fotoPengguna(User $user): BinaryFileResponse
    {
        $viewer = auth()->user();
        abort_unless($viewer?->is($user) || $viewer?->canPerform('users', 'view'), 403);

        return $this->privateImage((string) $user->foto_path);
    }

    private function authorizeLampiran(Lampiran $lampiran): void
    {
        $module = match (class_basename((string) $lampiran->entitas)) {
            'SuratMasuk', 'SuratKeluar' => 'surat',
            'Legalisir' => 'legalisir',
            'KenaikanPangkat', 'GajiBerkala', 'Pegawai' => 'kepegawaian',
            default => null,
        };

        abort_unless($module && auth()->user()?->canPerform($module, 'view'), 403);

        $entity = class_basename((string) $lampiran->entitas);
        $owner = match ($entity) {
            'SuratMasuk' => SuratMasuk::withTrashed()->find($lampiran->entitas_id),
            'SuratKeluar' => SuratKeluar::withTrashed()->find($lampiran->entitas_id),
            'Legalisir' => Legalisir::withTrashed()->find($lampiran->entitas_id),
            default => null,
        };

        if (in_array($entity, ['SuratMasuk', 'SuratKeluar', 'Legalisir'], true)) {
            $field = SecureDocumentPath::fieldForAttachment($lampiran);
            abort_unless($owner && $field, 404);
            SecureDocumentPath::validate($owner, $field, $lampiran->path);
        }
    }

    private function recordAccess(Lampiran $lampiran, string $action): void
    {
        LampiranAccessLog::create([
            'lampiran_id' => $lampiran->id,
            'user_id' => auth()->id(),
            'aksi' => $action,
            'ip' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 500),
            'created_at' => now(),
        ]);

        AuditLog::record('akses_lampiran_'.$action, class_basename((string) $lampiran->entitas), $lampiran->entitas_id, null, [
            'lampiran_id' => $lampiran->id,
            'nama_file' => $lampiran->nama_file,
            'versi' => $lampiran->versi,
        ]);
    }

    private function privateImage(string $path): BinaryFileResponse
    {
        $disk = Storage::disk('local');
        abort_unless($path !== '' && $disk->exists($path), 404);

        $mime = strtolower((string) $disk->mimeType($path));
        abort_unless(in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true), 415);

        return response()->file($disk->path($path), [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; sandbox",
            'Cache-Control' => 'private, max-age=300',
        ]);
    }
}
