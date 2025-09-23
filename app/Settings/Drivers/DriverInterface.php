<?php

namespace App\Settings\Drivers;

interface DriverInterface
{
    public function get($key, $default = null);
    public function set($key, $value);
    public function forget($key);
    public function all();
}