<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\TicketPriorityResource\Pages\CreateTicketPriority;
use App\Filament\Admin\Resources\TicketPriorityResource\Pages\EditTicketPriority;
use App\Filament\Admin\Resources\TicketPriorityResource\Pages\ListTicketPriorities;
use App\Models\TicketPriority;
use BackedEnum;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

class TicketPriorityResource extends Resource
{
    protected static ?string $model = TicketPriority::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-bolt';

    protected static string|UnitEnum|null $navigationGroup = 'Support';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Set $set, ?string $state): mixed => $set('slug', Str::slug((string) $state))),
                        TextInput::make('slug')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        TextInput::make('description')
                            ->columnSpanFull(),
                        ColorPicker::make('color')
                            ->default('#6B7280'),
                        TextInput::make('icon')
                            ->placeholder('heroicon-o-bolt'),
                        TextInput::make('level')
                            ->numeric()
                            ->default(1)
                            ->helperText('Higher level = more urgent.'),
                        TextInput::make('sla_hours')
                            ->numeric()
                            ->placeholder('e.g. 24'),
                        Toggle::make('is_active'),
                        TextInput::make('sort_order')
                            ->numeric()
                            ->default(0),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                ColorColumn::make('color')
                    ->label('Color'),
                TextColumn::make('level')
                    ->sortable(),
                TextColumn::make('sla_hours')
                    ->label('SLA (h)')
                    ->sortable()
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? $state : '—'),
                TextColumn::make('tickets_count')
                    ->counts('tickets')
                    ->label('Tickets')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                TextColumn::make('sort_order')
                    ->sortable()
                    ->toggleable()
                    ->toggledHiddenByDefault(),
            ])
            ->defaultSort('level')
            ->searchable();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTicketPriorities::route('/'),
            'create' => CreateTicketPriority::route('/create'),
            'edit' => EditTicketPriority::route('/{record}/edit'),
        ];
    }
}
