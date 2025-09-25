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

if (! function_exists('safeValue')) {
    /**
     * Get a safe value from object property or array key, return 0 if null or not exists
     *
     * @param mixed $data The data source (object or array)
     * @param string $key The property/key name
     * @param mixed $default Default value to return if not found (default: 0)
     * @return mixed
     */
    function safeValue($data, string $key, $default = 0)
    {
        if ($data === null) {
            return $default;
        }

        // Handle object properties
        if (is_object($data)) {
            return $data->$key ?? $default;
        }

        // Handle array keys
        if (is_array($data)) {
            return $data[$key] ?? $default;
        }

        return $default;
    }
}

if (! function_exists('safeNumericValue')) {
    /**
     * Get a safe numeric value from object property or array key, return 0 if null, not exists, or not numeric
     *
     * @param mixed $data The data source (object or array)
     * @param string $key The property/key name
     * @param float $default Default value to return if not found (default: 0)
     * @return float
     */
    function safeNumericValue($data, string $key, float $default = 0.0): float
    {
        $value = safeValue($data, $key, null);

        if ($value === null || $value === '') {
            return $default;
        }

        // Convert to float if it's numeric
        if (is_numeric($value)) {
            return floatval($value);
        }

        return $default;
    }
}
