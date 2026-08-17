<?php

namespace App\Filament\Admin\Resources\TicketCategoryResource\Pages;

use App\Filament\Admin\Resources\TicketCategoryResource;
use App\Models\TicketCategory;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTicketCategory extends EditRecord
{
    protected static string $resource = TicketCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->disabled(fn (TicketCategory $record): bool => $record->tickets()->exists())
                ->tooltip(fn (TicketCategory $record): ?string => $record->tickets()->exists() ? 'In use by tickets — cannot delete.' : null),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
