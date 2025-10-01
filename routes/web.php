<?php

use App\Enums\RoleType;
use App\Models\Group;
use App\Models\Menu;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/logout', function () {
    auth()->logout();
    return redirect('/');
})->name('logout');

Route::middleware([
    'auth',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    foreach (RoleType::getValues() as $role) {

        Route::get('/dashboard', [\App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');
        Route::get('/profile', [\App\Http\Controllers\UserController::class, 'getProfile'])->name('profile');

        // Load menu and group data with error handling for migrations
        $menu = collect();
        $groups = collect();

        try {
            $menu = Menu::all();
            $groups = Group::orderBy('group_sort')->get();
        } catch (\Exception $e) {
            // Database not ready during migration
            $menu = collect();
            $groups = collect();
        }

        foreach ($groups as $group) {
            Route::prefix($group->field_key)->group(function () use ($menu, $group) {
                foreach ($menu->where('menu_group', $group->field_key) as $item) {
                    if (class_exists($item->menu_controller)) {
                        Route::auto($item->menu_code, $item->menu_controller);
                    }
                }
            });
        }
    }
});
