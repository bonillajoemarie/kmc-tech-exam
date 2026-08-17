<?php

namespace Database\Seeders;

use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use Illuminate\Database\Seeder;

class TicketLookupSeeder extends Seeder
{
    public function run(): void
    {
        TicketStatus::firstOrCreate(['slug' => 'open'], [
            'name' => 'Open',
            'description' => 'Ticket has been submitted and is awaiting attention.',
            'color' => '#22C55E',
            'is_default' => true,
            'is_closed' => false,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        TicketStatus::firstOrCreate(['slug' => 'pending'], [
            'name' => 'Pending',
            'description' => 'Ticket is awaiting a response or further action.',
            'color' => '#F59E0B',
            'is_default' => false,
            'is_closed' => false,
            'is_active' => true,
            'sort_order' => 2,
        ]);

        TicketStatus::firstOrCreate(['slug' => 'resolved'], [
            'name' => 'Resolved',
            'description' => 'Ticket has been resolved and closed.',
            'color' => '#3B82F6',
            'is_default' => false,
            'is_closed' => true,
            'is_active' => true,
            'sort_order' => 3,
        ]);

        TicketPriority::firstOrCreate(['slug' => 'low'], [
            'name' => 'Low',
            'description' => 'Non-urgent, can be handled in normal turnaround.',
            'color' => '#6B7280',
            'level' => 1,
            'sla_hours' => 72,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        TicketPriority::firstOrCreate(['slug' => 'medium'], [
            'name' => 'Medium',
            'description' => 'Standard priority with normal turnaround.',
            'color' => '#3B82F6',
            'level' => 2,
            'sla_hours' => 48,
            'is_active' => true,
            'sort_order' => 2,
        ]);

        TicketPriority::firstOrCreate(['slug' => 'high'], [
            'name' => 'High',
            'description' => 'Urgent issue affecting service.',
            'color' => '#EF4444',
            'level' => 3,
            'sla_hours' => 24,
            'is_active' => true,
            'sort_order' => 3,
        ]);

        TicketPriority::firstOrCreate(['slug' => 'urgent'], [
            'name' => 'Urgent',
            'description' => 'Critical issue requiring immediate attention.',
            'color' => '#7C3AED',
            'level' => 4,
            'sla_hours' => 4,
            'is_active' => true,
            'sort_order' => 4,
        ]);

        $categories = [
            ['name' => 'Billing', 'slug' => 'billing', 'description' => 'Invoices, payments, and account charges.', 'color' => '#10B981'],
            ['name' => 'Technical Issue', 'slug' => 'technical-issue', 'description' => 'Bugs, errors, and system malfunctions.', 'color' => '#EF4444'],
            ['name' => 'Account Access', 'slug' => 'account-access', 'description' => 'Login problems, permissions, and account recovery.', 'color' => '#F59E0B'],
            ['name' => 'Feature Request', 'slug' => 'feature-request', 'description' => 'Suggestions for new or improved functionality.', 'color' => '#8B5CF6'],
            ['name' => 'Other', 'slug' => 'other', 'description' => 'Anything that does not fit another category.', 'color' => '#6B7280'],
        ];

        foreach ($categories as $index => $category) {
            TicketCategory::firstOrCreate(['slug' => $category['slug']], array_merge($category, [
                'is_active' => true,
                'sort_order' => $index + 1,
            ]));
        }
    }
}
