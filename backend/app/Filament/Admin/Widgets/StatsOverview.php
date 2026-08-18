<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Ticket;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $openQuery = fn () => Ticket::query()->open();

        return [
            Stat::make('Open tickets', $openQuery()->count())
                ->description('Not in a closed status')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),
            Stat::make('Unassigned', $openQuery()->whereNull('assigned_to')->count())
                ->description('Waiting for an assignee')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('warning'),
            Stat::make('Overdue', $openQuery()->whereNotNull('due_at')->where('due_at', '<', now())->count())
                ->description('Past their due date')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger'),
            Stat::make('Resolved today', Ticket::query()->whereDate('resolved_at', today())->count())
                ->description('Since midnight')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('info'),
        ];
    }
}
