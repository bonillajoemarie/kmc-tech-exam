<?php

namespace App\Filament\Admin\Widgets;

use App\Models\TicketStatus;
use Filament\Widgets\ChartWidget;

class TicketsByStatusChart extends ChartWidget
{
    protected ?string $heading = 'Tickets by status';

    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        $statuses = TicketStatus::query()
            ->withCount('tickets')
            ->orderBy('sort_order')
            ->get();

        return [
            'labels' => $statuses->pluck('name')->all(),
            'datasets' => [
                [
                    'label' => 'Tickets',
                    'data' => $statuses->pluck('tickets_count')->all(),
                    'backgroundColor' => $statuses->pluck('color')->all(),
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
