<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\WorkspaceOverviewWidget;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Contracts\Support\Htmlable;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?string $title = 'Ruang Kerja Tata Usaha';

    public function getHeading(): string | Htmlable
    {
        return '';
    }

    public function getSubheading(): string | Htmlable | null
    {
        return null;
    }

    public function getWidgets(): array
    {
        return [
            WorkspaceOverviewWidget::class,
        ];
    }

    public function getColumns(): int | string | array
    {
        return 1;
    }
}
