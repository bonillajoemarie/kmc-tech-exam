<?php

namespace Database\Seeders;

use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketComment;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call(TicketLookupSeeder::class);

        $adminRole = Role::findOrCreate('admin');
        $customerRole = Role::findOrCreate('customer');

        $admin = User::firstOrCreate(['email' => 'admin@example.com'], [
            'name' => 'Admin User',
            'password' => 'password',
        ])->assignRole($adminRole);

        $customers = [
            User::firstOrCreate(['email' => 'customer@example.com'], [
                'name' => 'Customer User',
                'password' => 'password',
            ])->assignRole($customerRole),
            User::firstOrCreate(['email' => 'jane@example.com'], [
                'name' => 'Jane Doe',
                'password' => 'password',
            ])->assignRole($customerRole),
        ];

        $statuses = TicketStatus::all();
        $priorities = TicketPriority::all();
        $categories = TicketCategory::all();

        for ($i = 0; $i < 25; $i++) {
            $customer = $customers[array_rand($customers)];

            $ticket = Ticket::factory()->create([
                'user_id' => $customer->id,
                'category_id' => $categories->random()->id,
                'priority_id' => $priorities->random()->id,
                'status_id' => $statuses->random()->id,
            ]);

            $commentCount = rand(0, 4);

            for ($c = 0; $c < $commentCount; $c++) {
                TicketComment::factory()->create([
                    'ticket_id' => $ticket->id,
                    'user_id' => fake()->boolean(50) ? $admin->id : $customer->id,
                    'is_internal' => false,
                ]);
            }
        }
    }
}
