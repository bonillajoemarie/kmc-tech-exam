<?php

namespace Database\Factories;

use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketComment>
 */
class TicketCommentFactory extends Factory
{
    protected $model = TicketComment::class;

    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory(),
            'user_id' => function (array $attributes) {
                $owner = Ticket::find($attributes['ticket_id'])?->user_id;
                $admin = User::role('admin')->inRandomOrder()->first()?->id;
                $pool = array_filter([$owner, $admin]);

                return $pool ? fake()->randomElement($pool) : User::factory();
            },
            'content' => fake()->paragraph(),
            'is_internal' => false,
        ];
    }
}
