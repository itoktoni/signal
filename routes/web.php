<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware([
    'auth',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', [\App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');

    // Route::resource('users', \App\Http\Controllers\UserController::class);
    Route::auto('user', \App\Http\Controllers\UserController::class);
    Route::get('/profile', [\App\Http\Controllers\UserController::class, 'getProfile'])->name('profile');
});
