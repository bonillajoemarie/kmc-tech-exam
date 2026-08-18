<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\TicketResource\Pages\CreateTicket;
use App\Filament\Admin\Resources\TicketResource\Pages\EditTicket;
use App\Filament\Admin\Resources\TicketResource\Pages\ListTickets;
use App\Filament\Admin\Resources\TicketResource\Pages\ViewTicket;
use App\Filament\Admin\Resources\TicketResource\RelationManagers\CommentsRelationManager;
use App\Models\Ticket;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Colors\Color;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use UnitEnum;

class TicketResource extends Resource
{
    protected static ?string $model = Ticket::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-lifebuoy';

    protected static string|UnitEnum|null $navigationGroup = 'Support';

    protected static ?string $recordTitleAttribute = 'subject';

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return 'Tickets';
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) Ticket::query()->open()->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    /**
     * @return array<int, string> of admin user names keyed by user id.
     */
    public static function adminUserOptions(): array
    {
        return User::query()
            ->whereHas('roles', fn ($query) => $query->where('name', 'admin'))
            ->pluck('name', 'id')
            ->all();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Ticket details')
                    ->description('Who raised it, what it is about, and where it sits in the workflow.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('subject')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Textarea::make('description')
                            ->required()
                            ->rows(5)
                            ->columnSpanFull(),
                        Select::make('user_id')
                            ->label('Customer')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('category_id')
                            ->label('Category')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload(),
                        Select::make('priority_id')
                            ->label('Priority')
                            ->relationship('priority', 'name')
                            ->searchable()
                            ->preload()
                            ->default(fn (): ?int => TicketPriority::active()->orderBy('level')->value('id')),
                        Select::make('status_id')
                            ->label('Status')
                            ->relationship('status', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->default(fn (): ?int => TicketStatus::default()->value('id')),
                        Select::make('assigned_to')
                            ->label('Assigned to')
                            ->relationship('assignee', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable(),
                    ]),
                Section::make('SLA & resolution')
                    ->description('Deadlines and resolution timestamps.')
                    ->columns(3)
                    ->schema([
                        DateTimePicker::make('due_at')
                            ->label('Due at'),
                        DateTimePicker::make('resolved_at')
                            ->label('Resolved at'),
                        DateTimePicker::make('closed_at')
                            ->label('Closed at'),
                    ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                TextEntry::make('ticket_number')
                    ->label('Ticket #')
                    ->badge(),
                TextEntry::make('status.name')
                    ->label('Status')
                    ->badge()
                    ->color(fn (Ticket $record): ?string => $record->status?->color),
                TextEntry::make('priority.name')
                    ->label('Priority')
                    ->badge()
                    ->color(fn (Ticket $record): ?string => $record->priority?->color),
                TextEntry::make('user.name')
                    ->label('Customer'),
                TextEntry::make('category.name')
                    ->label('Category')
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? $state : 'None'),
                TextEntry::make('assignee.name')
                    ->label('Assigned to')
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? $state : 'Unassigned'),
                TextEntry::make('subject')
                    ->columnSpanFull(),
                TextEntry::make('description')
                    ->columnSpanFull(),
                TextEntry::make('created_at')
                    ->label('Created')
                    ->dateTime(),
                TextEntry::make('due_at')
                    ->label('Due')
                    ->dateTime()
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? $state : 'None'),
                TextEntry::make('resolved_at')
                    ->label('Resolved')
                    ->dateTime()
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? $state : 'Not resolved yet'),
                TextEntry::make('closed_at')
                    ->label('Closed')
                    ->dateTime()
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? $state : 'Still open'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ticket_number')
                    ->label('Ticket')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('subject')
                    ->searchable()
                    ->sortable()
                    ->limit(60),
                TextColumn::make('user.name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('category.name')
                    ->label('Category')
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? $state : '—')
                    ->toggleable(),
                TextColumn::make('priority.name')
                    ->label('Priority')
                    ->badge()
                    ->color(fn (Ticket $record): ?string => $record->priority?->color)
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('status.name')
                    ->label('Status')
                    ->badge()
                    ->color(fn (Ticket $record): ?string => $record->status?->color)
                    ->sortable(),
                TextColumn::make('assignee.name')
                    ->label('Assigned to')
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? $state : '—')
                    ->toggleable(),
                TextColumn::make('due_at')
                    ->label('Due')
                    ->dateTime()
                    ->sortable()
                    ->color(fn (Ticket $record): ?string => $record->due_at !== null && $record->due_at->isPast() && ! $record->isClosed() ? 'danger' : null)
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable()
                    ->toggledHiddenByDefault(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->relationship('status', 'name'),
                SelectFilter::make('priority')
                    ->relationship('priority', 'name'),
                SelectFilter::make('category')
                    ->relationship('category', 'name'),
                SelectFilter::make('assigned_to')
                    ->label('Assigned to')
                    ->attribute('assigned_to')
                    ->options(fn (): array => static::adminUserOptions()),
                TrashedFilter::make(),
            ])
            ->recordActions([
                Action::make('changeStatus')
                    ->label('Change status')
                    ->icon('heroicon-o-arrow-path')
                    ->color(Color::Amber)
                    ->schema([
                        Select::make('status_id')
                            ->label('Status')
                            ->options(fn (): array => TicketStatus::active()->pluck('name', 'id')->all())
                            ->required(),
                    ])
                    ->fillForm(fn (Ticket $record): array => ['status_id' => $record->status_id])
                    ->modalSubmitActionLabel('Save status')
                    ->action(function (Ticket $record, array $data): void {
                        $record->update(['status_id' => $data['status_id']]);

                        Notification::make()
                            ->title('Status updated')
                            ->success()
                            ->send();
                    }),
                Action::make('assign')
                    ->label('Assign')
                    ->icon('heroicon-o-user-plus')
                    ->color(Color::Violet)
                    ->schema([
                        Select::make('assigned_to')
                            ->label('Assigned to')
                            ->options(fn (): array => static::adminUserOptions())
                            ->placeholder('Unassigned')
                            ->nullable(),
                    ])
                    ->fillForm(fn (Ticket $record): array => ['assigned_to' => $record->assigned_to])
                    ->modalSubmitActionLabel('Assign ticket')
                    ->action(function (Ticket $record, array $data): void {
                        $record->update(['assigned_to' => filled($data['assigned_to']) ? $data['assigned_to'] : null]);

                        Notification::make()
                            ->title('Ticket assigned')
                            ->success()
                            ->send();
                    }),
                Action::make('markResolved')
                    ->label('Mark resolved')
                    ->icon('heroicon-o-check-circle')
                    ->color(Color::Emerald)
                    ->requiresConfirmation()
                    ->modalHeading('Mark this ticket as resolved?')
                    ->visible(fn (Ticket $record): bool => $record->resolved_at === null && ! $record->isClosed())
                    ->action(function (Ticket $record): void {
                        $resolved = TicketStatus::query()->where('slug', 'resolved')->first()
                            ?? TicketStatus::query()->where('is_closed', true)->first();

                        $record->update([
                            'status_id' => $resolved?->id ?? $record->status_id,
                            'resolved_at' => now(),
                        ]);

                        Notification::make()
                            ->title('Ticket resolved')
                            ->success()
                            ->send();
                    }),
                Action::make('close')
                    ->label('Close')
                    ->icon('heroicon-o-x-circle')
                    ->color(Color::Rose)
                    ->requiresConfirmation()
                    ->modalHeading('Close this ticket?')
                    ->visible(fn (Ticket $record): bool => $record->closed_at === null)
                    ->action(function (Ticket $record): void {
                        $record->update(['closed_at' => now()]);

                        Notification::make()
                            ->title('Ticket closed')
                            ->success()
                            ->send();
                    }),
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    DeleteAction::make(),
                ])
                    ->label('More')
                    ->icon('heroicon-m-ellipsis-vertical'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->searchable()
            ->poll('15s');
    }

    public static function getRelations(): array
    {
        return [
            CommentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTickets::route('/'),
            'create' => CreateTicket::route('/create'),
            'view' => ViewTicket::route('/{record}'),
            'edit' => EditTicket::route('/{record}/edit'),
        ];
    }
}
