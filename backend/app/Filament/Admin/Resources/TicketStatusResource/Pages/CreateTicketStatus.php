<?php

namespace App\Filament\Admin\Resources\TicketStatusResource\Pages;

use App\Filament\Admin\Resources\TicketStatusResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTicketStatus extends CreateRecord
{
    protected static string $resource = TicketStatusResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
