<?php

namespace App\Filament\Admin\Resources\TicketPriorityResource\Pages;

use App\Filament\Admin\Resources\TicketPriorityResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTicketPriority extends CreateRecord
{
    protected static string $resource = TicketPriorityResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
