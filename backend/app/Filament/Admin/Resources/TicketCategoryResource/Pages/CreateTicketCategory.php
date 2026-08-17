<?php

namespace App\Filament\Admin\Resources\TicketCategoryResource\Pages;

use App\Filament\Admin\Resources\TicketCategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTicketCategory extends CreateRecord
{
    protected static string $resource = TicketCategoryResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
