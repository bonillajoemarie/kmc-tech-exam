<?php

namespace App\Filament\Http\Middleware;

use App\Models\User;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasAdminRole
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Filament::auth()->user();

        if (! $user instanceof User || ! $user->hasRole('admin')) {
            abort(403, 'You do not have permission to access the admin panel.');
        }

        return $next($request);
    }
}
