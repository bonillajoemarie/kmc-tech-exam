<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use Illuminate\Database\Eloquent\Collection;

class MetaController extends Controller
{
    public function categories(): Collection
    {
        return TicketCategory::active()->orderBy('sort_order')->get(['id', 'name', 'slug', 'color']);
    }

    public function priorities(): Collection
    {
        return TicketPriority::active()->orderBy('sort_order')->get(['id', 'name', 'slug', 'color']);
    }

    public function statuses(): Collection
    {
        return TicketStatus::active()->orderBy('sort_order')->get(['id', 'name', 'slug', 'color']);
    }
}
