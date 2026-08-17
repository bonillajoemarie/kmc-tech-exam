<?php

namespace App\Filament\Admin\Resources\TicketCategoryResource\Pages;

use App\Filament\Admin\Resources\TicketCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTicketCategories extends ListRecords
{
    protected static string $resource = TicketCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
