<?php

namespace App\Providers;

use App\Models\Group;
use App\Models\Menu;
use App\Settings\Settings;
use App\Settings\Drivers\FileDriver;
use Illuminate\Support\Facades;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Artisan;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind Settings to FileDriver
        $this->app->singleton(Settings::class, function ($app) {
            $driver = new FileDriver(['path' => storage_path('app/settings.json')]);
            return new Settings($driver);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Load helpers
        require_once app_path('Helpers/Global.php');

        // Share controller context with all views (lazy loading for database data)
        Facades\View::composer('*', function (View $view) {
            $context = $this->getControllerContext();

            // Load menu and group data only when needed (lazy loading)
            $menu = collect();
            $group = collect();

            try {
                $menu = Menu::orderBy('menu_sort')->get();
                $group = Group::orderBy('group_sort')->get();
            } catch (\Exception $e) {
                // Database not ready or tables don't exist
                $menu = collect();
                $group = collect();
            }

            $context['menu'] = $menu;
            $context['group'] = $group;
            $view->with('context', $context);
        });
    }

    /**
     * Get controller context information
     */
    private function getControllerContext()
    {
        $request = request();
        $route = $request->route();

        if (! $route) {
            return [
                'controller' => null,
                'controller_short' => null,
                'module' => null,
                'module_plural' => null,
                'action' => null,
                'current_route' => null,
                'is_create' => false,
                'is_edit' => false,
                'is_index' => false,
                'is_show' => false,
            ];
        }

        $controller = $route->getController();

        if (! $controller) {
            return [
                'controller' => null,
                'controller_short' => null,
                'module' => null,
                'module_plural' => null,
                'action' => null,
                'current_route' => $route->getName(),
                'is_create' => false,
                'is_edit' => false,
                'is_index' => false,
                'is_show' => false,
            ];
        }

        $className = get_class($controller);
        $shortName = class_basename($className);
        $controllerShort = str_replace('Controller', '', $shortName);
        $module = strtolower($controllerShort);
        $pluralModule = $this->pluralize($module);

        $action = $route->getActionMethod();
        $cleanAction = strtolower(preg_replace('/^(get|post)/', '', $action));

        return [
            'controller' => $shortName,
            'controller_short' => $controllerShort,
            'module' => $module,
            'title' => ucfirst($module),
            'module_plural' => $pluralModule,
            'action' => $cleanAction,
            'current_route' => $route->getName(),
            'is_create' => $cleanAction === 'create',
            'is_edit' => $cleanAction === 'update',
            'is_index' => in_array($cleanAction, ['index', 'data']),
            'is_show' => $cleanAction === 'show',
        ];
    }

    /**
     * Simple pluralization function
     */
    private function pluralize(string $word): string
    {
        if (substr($word, -1) === 'y') {
            return substr($word, 0, -1).'ies';
        } elseif (substr($word, -1) === 's' || substr($word, -2) === 'es') {
            return $word;
        } else {
            return $word.'s';
        }
    }
}
