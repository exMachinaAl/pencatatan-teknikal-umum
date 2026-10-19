<?php

namespace App\Filament\Resources\NotificationOutboxResource\Pages;

use App\Filament\Resources\NotificationOutboxResource;
use Filament\Resources\Pages\ListRecords;

class ListNotificationOutboxes extends ListRecords
{
    protected static string $resource = NotificationOutboxResource::class;
}
