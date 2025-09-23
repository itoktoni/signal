<?php

namespace App\Settings\Drivers;

class MemoryDriver implements DriverInterface
{
    protected $data = [];

    public function __construct($config)
    {
        // No config needed
    }

    public function get($key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }

    public function set($key, $value)
    {
        $this->data[$key] = $value;
    }

    public function forget($key)
    {
        unset($this->data[$key]);
    }

    public function all()
    {
        return $this->data;
    }
}