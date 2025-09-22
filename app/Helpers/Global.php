<?php

if (!function_exists('sortUrl')) {
    function sortUrl($sort, $route)
    {
        $direction = request('sort') === $sort && request('direction') === 'asc' ? 'desc' : 'asc';
        return route($route, array_merge(request()->query(), ['sort' => $sort, 'direction' => $direction]));
    }
}