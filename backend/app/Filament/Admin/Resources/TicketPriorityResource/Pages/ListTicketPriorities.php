<?php

namespace App\Filament\Admin\Resources\TicketPriorityResource\Pages;

use App\Filament\Admin\Resources\TicketPriorityResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTicketPriorities extends ListRecords
{
    protected static string $resource = TicketPriorityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
