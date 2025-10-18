<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CoinController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

});

Route::get('testing/{code}', [CoinController::class, 'getTest']);

// Route::auto('user', UserController::class);
