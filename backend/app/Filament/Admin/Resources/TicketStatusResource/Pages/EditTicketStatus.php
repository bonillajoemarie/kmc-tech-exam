<?php

namespace App\Filament\Admin\Resources\TicketStatusResource\Pages;

use App\Filament\Admin\Resources\TicketStatusResource;
use App\Models\TicketStatus;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTicketStatus extends EditRecord
{
    protected static string $resource = TicketStatusResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->disabled(fn (TicketStatus $record): bool => $record->tickets()->exists())
                ->tooltip(fn (TicketStatus $record): ?string => $record->tickets()->exists() ? 'In use by tickets — cannot delete.' : null),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
