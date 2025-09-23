<?php

namespace App\Settings\Drivers;

use Memcache;

class MemcacheDriver implements DriverInterface
{
    protected $memcache;

    public function __construct($config)
    {
        $this->memcache = new Memcache;
        $this->memcache->addServer($config['host'] ?? '127.0.0.1', $config['port'] ?? 11211);
    }

    public function get($key, $default = null)
    {
        $value = $this->memcache->get('settings:'.$key);

        return $value !== false ? unserialize($value) : $default;
    }

    public function set($key, $value)
    {
        $this->memcache->set('settings:'.$key, serialize($value));
    }

    public function forget($key)
    {
        $this->memcache->delete('settings:'.$key);
    }

    public function all()
    {
        // Memcache doesn't have a way to get all keys easily, so return empty
        return [];
    }
}
