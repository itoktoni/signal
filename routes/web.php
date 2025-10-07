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

        // Trade module routes
        Route::prefix('trade')->name('trade.')->group(function () {
            Route::get('/', [\App\Http\Controllers\TradeController::class, 'index'])->name('index');
            Route::get('/data', [\App\Http\Controllers\TradeController::class, 'getData'])->name('getData');
            Route::get('/create', [\App\Http\Controllers\TradeController::class, 'getCreate'])->name('getCreate');
            Route::post('/create', [\App\Http\Controllers\TradeController::class, 'postCreate'])->name('postCreate');
            Route::get('/show/{tradeId}', [\App\Http\Controllers\TradeController::class, 'getShow'])->name('getShow');
            Route::get('/update/{tradeId}', [\App\Http\Controllers\TradeController::class, 'getUpdate'])->name('getUpdate');
            Route::post('/update/{tradeId}', [\App\Http\Controllers\TradeController::class, 'postUpdate'])->name('postUpdate');
            Route::get('/delete/{tradeId}', [\App\Http\Controllers\TradeController::class, 'getDelete'])->name('getDelete');
            Route::post('/delete/{tradeId}', [\App\Http\Controllers\TradeController::class, 'postDelete'])->name('postDelete');
            Route::post('/bulk-delete', [\App\Http\Controllers\TradeController::class, 'postBulkDelete'])->name('postBulkDelete');

            // Trade execution routes
            Route::post('/execute/{tradeId}', [\App\Http\Controllers\TradeController::class, 'postExecute'])->name('postExecute');
            Route::post('/cancel/{tradeId}', [\App\Http\Controllers\TradeController::class, 'postCancel'])->name('postCancel');
            Route::get('/status/{tradeId}', [\App\Http\Controllers\TradeController::class, 'getStatus'])->name('getStatus');
            Route::get('/sync', [\App\Http\Controllers\TradeController::class, 'getSync'])->name('getSync');
        });
    }
});
