<?php

namespace App\Filament\Admin\Resources\TicketResource\RelationManagers;

use App\Models\TicketComment;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CommentsRelationManager extends RelationManager
{
    protected static string $relationship = 'comments';

    protected static ?string $inverseRelationship = 'ticket';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('content')
                    ->required()
                    ->rows(3),
                FileUpload::make('attachments')
                    ->label('Attachments')
                    ->multiple()
                    ->disk('public')
                    ->directory('attachments')
                    ->maxFiles(5)
                    ->maxSize(10240)
                    ->acceptedFileTypes(TicketComment::ATTACHMENT_MIMES)
                    ->columnSpanFull(),
                Toggle::make('is_internal')
                    ->label('Internal note')
                    ->helperText('Only visible to staff, never sent to the customer.'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('content')
            ->columns([
                TextColumn::make('user.name')
                    ->label('Author')
                    ->badge()
                    ->sortable(),
                TextColumn::make('content')
                    ->wrap()
                    ->limit(120)
                    ->searchable(),
                IconColumn::make('is_internal')
                    ->label('Internal')
                    ->boolean(),
                TextColumn::make('attachments')
                    ->label('Attachments')
                    ->html()
                    ->formatStateUsing(fn (TicketComment $record): string => collect($record->attachmentUrls())
                        ->map(fn (array $attachment): string => sprintf(
                            '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
                            e($attachment['url']),
                            e($attachment['name']),
                        ))
                        ->implode('<br>')),
                TextColumn::make('created_at')
                    ->label('Posted')
                    ->dateTime()
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateDataUsing(fn (array $data): array => [...$data, 'user_id' => (int) Filament::auth()->user()?->getAuthIdentifier()]),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('15s');
    }
}
