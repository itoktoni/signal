<?php

namespace App\Settings;

use App\Settings\Drivers\DriverInterface;
use Illuminate\Support\Facades\Cache;

class Settings
{
    protected $driver;

    public function __construct(DriverInterface $driver)
    {
        $this->driver = $driver;
    }

    public function get($key, $default = null)
    {
        $cacheKey = 'settings.' . $key;

        if (config('settings.cache')) {
            return Cache::rememberForever($cacheKey, function () use ($key, $default) {
                return $this->driver->get($key, $default);
            });
        }

        return $this->driver->get($key, $default);
    }

    public function set($key, $value)
    {
        $this->driver->set($key, $value);

        if (config('settings.cache')) {
            Cache::forget('settings.' . $key);
        }
    }

    public function forget($key)
    {
        $this->driver->forget($key);

        if (config('settings.cache')) {
            Cache::forget('settings.' . $key);
        }
    }

    public function all()
    {
        return $this->driver->all();
    }
}