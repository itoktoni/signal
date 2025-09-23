<?php

namespace App\Settings\Drivers;

use Illuminate\Support\Facades\Redis;

class RedisDriver implements DriverInterface
{
    protected $connection;

    public function __construct($config)
    {
        $this->connection = $config['connection'] ?? 'default';
    }

    public function get($key, $default = null)
    {
        $value = Redis::connection($this->connection)->get('settings:'.$key);

        return $value ? unserialize($value) : $default;
    }

    public function set($key, $value)
    {
        Redis::connection($this->connection)->set('settings:'.$key, serialize($value));
    }

    public function forget($key)
    {
        Redis::connection($this->connection)->del('settings:'.$key);
    }

    public function all()
    {
        $keys = Redis::connection($this->connection)->keys('settings:*');
        $data = [];
        foreach ($keys as $key) {
            $k = str_replace('settings:', '', $key);
            $data[$k] = unserialize(Redis::connection($this->connection)->get($key));
        }

        return $data;
    }
}
