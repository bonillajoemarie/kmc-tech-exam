<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MetaController;
use App\Http\Controllers\Api\TicketController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register'])->middleware('throttle:10,1');
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');
        Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:api');
    });

    Route::middleware('auth:api')->group(function () {
        Route::get('user', [AuthController::class, 'user']);

        Route::get('meta/categories', [MetaController::class, 'categories']);
        Route::get('meta/priorities', [MetaController::class, 'priorities']);
        Route::get('meta/statuses', [MetaController::class, 'statuses']);

        Route::get('tickets', [TicketController::class, 'index']);
        Route::post('tickets', [TicketController::class, 'store'])->middleware('throttle:60,1');
        Route::get('tickets/{ticket}', [TicketController::class, 'show']);
        Route::post('tickets/{ticket}/comments', [TicketController::class, 'addComment'])->middleware('throttle:60,1');
    });
});
