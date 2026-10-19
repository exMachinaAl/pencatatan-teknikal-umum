<?php
namespace App\Filament\Pages;

use App\Support\TuAccess;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TuSecurityOverview extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-shield-check';
    protected static ?string $navigationGroup = 'Pengaturan & Utilitas';
    protected static ?string $navigationLabel = 'Ringkasan Hak Akses';
    protected static ?string $title = 'Ringkasan Hak Akses & Audit';
    protected static ?int $navigationSort = 6;
    protected static string $view = 'filament.pages.tu-security-overview';

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return (bool) $user?->canPerform('audit', 'view');
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    public function permissionRows(): array
    {
        $rows = [];
        foreach (TuAccess::LABELS as $key => $name) {
            $cells = [];
            foreach (TuAccess::roles() as $role => $label) {
                $cells[$role] = TuAccess::summary($role, $key);
            }
            $rows[] = ['module' => $name, 'access' => $cells];
        }
        return $rows;
    }

    public function recentAudit(): array
    {
        return DB::table('audit_log')
            ->select(['user_name', 'aksi', 'entitas', 'created_at'])
            ->orderByDesc('id')->limit(12)->get()
            ->map(fn ($row) => [
                'user' => (string) ($row->user_name ?: 'Sistem'),
                'action' => (string) $row->aksi,
                'entity' => (string) ($row->entitas ?: '-'),
                'when' => (string) $row->created_at,
            ])->all();
    }
}
