<?php

namespace Database\Factories;

use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    public function definition(): array
    {
        return [
            'ticket_number' => 'TK-'.strtoupper(Str::random(6)).'-'.now()->format('ymd'),
            'subject' => fake()->sentence(6),
            'description' => fake()->paragraph(3),
            'user_id' => User::inRandomOrder()->first()?->id ?? User::factory(),
            'category_id' => TicketCategory::inRandomOrder()->first()?->id ?? TicketCategory::factory(),
            'priority_id' => TicketPriority::inRandomOrder()->first()?->id ?? TicketPriority::factory(),
            'status_id' => TicketStatus::inRandomOrder()->first()?->id ?? TicketStatus::factory(),
            'assigned_to' => null,
            'metadata' => null,
            'due_at' => null,
            'resolved_at' => null,
            'closed_at' => null,
        ];
    }
}
