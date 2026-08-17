<?php

namespace App\Filament\Admin\Resources\UserResource\Pages;

use App\Filament\Admin\Resources\UserResource;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->disabled(fn (User $record): bool => $record->getKey() === (int) Filament::auth()->user()?->getAuthIdentifier())
                ->tooltip(fn (User $record): ?string => $record->getKey() === (int) Filament::auth()->user()?->getAuthIdentifier() ? 'You cannot delete your own account.' : null),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
