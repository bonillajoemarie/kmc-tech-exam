<?php

namespace App\Filament\Admin\Resources\TicketPriorityResource\Pages;

use App\Filament\Admin\Resources\TicketPriorityResource;
use App\Models\TicketPriority;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTicketPriority extends EditRecord
{
    protected static string $resource = TicketPriorityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->disabled(fn (TicketPriority $record): bool => $record->tickets()->exists())
                ->tooltip(fn (TicketPriority $record): ?string => $record->tickets()->exists() ? 'In use by tickets — cannot delete.' : null),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
