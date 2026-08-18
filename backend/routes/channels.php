<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('user.{id}', function (User $user, int $id) {
    return $user->getAuthIdentifier() === $id;
});

Broadcast::channel('staff', function (User $user) {
    return $user->hasRole('admin');
});
