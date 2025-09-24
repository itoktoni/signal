<?php

if (! function_exists('sortUrl')) {
    function sortUrl($sort, $route)
    {
        $direction = request('sort') === $sort && request('direction') === 'asc' ? 'desc' : 'asc';

        return route($route, array_merge(request()->query(), ['sort' => $sort, 'direction' => $direction]));
    }
}

if (! function_exists('module')) {
    function module($action = null)
    {
        $route = request()->route();
        if ($route) {
            $controller = $route->getController();
            if (method_exists($controller, 'module')) {
                return $controller->module($action);
            }
        }
        return null;
    }
}
