<?php

namespace App\Settings;

use App\Settings\Drivers\DatabaseDriver;
use App\Settings\Drivers\FileDriver;
use App\Settings\Drivers\MemcacheDriver;
use App\Settings\Drivers\MemoryDriver;
use App\Settings\Drivers\RedisDriver;
use Illuminate\Support\Manager;

class SettingsManager extends Manager
{
    public function getDefaultDriver()
    {
        return config('settings.driver', 'database');
    }

    protected function createDatabaseDriver()
    {
        $config = config('settings.drivers.database');

        return new DatabaseDriver($config);
    }

    protected function createFileDriver()
    {
        $config = config('settings.drivers.file');

        return new FileDriver($config);
    }

    protected function createMemoryDriver()
    {
        $config = config('settings.drivers.memory');

        return new MemoryDriver($config);
    }

    protected function createRedisDriver()
    {
        $config = config('settings.drivers.redis');

        return new RedisDriver($config);
    }

    protected function createMemcacheDriver()
    {
        $config = config('settings.drivers.memcache');

        return new MemcacheDriver($config);
    }
}
