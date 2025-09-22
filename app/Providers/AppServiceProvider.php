<?php

namespace App\Providers;

use Illuminate\Support\Facades;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Load helpers
        require_once app_path('Helpers/Global.php');

        // Share controller context with all views
        Facades\View::composer('*', function (View $view) {
            $context = $this->getControllerContext();
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

        if (!$route) {
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

        if (!$controller) {
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
            return substr($word, 0, -1) . 'ies';
        } elseif (substr($word, -1) === 's' || substr($word, -2) === 'es') {
            return $word;
        } else {
            return $word . 's';
        }
    }
}
