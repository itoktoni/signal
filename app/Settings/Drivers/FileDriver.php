<?php

namespace App\Settings\Drivers;

use Illuminate\Support\Facades\File;

class FileDriver implements DriverInterface
{
    protected $path;

    public function __construct($config)
    {
        $this->path = $config['path'] ?? storage_path('app/settings.json');
    }

    protected function load()
    {
        if (! File::exists($this->path)) {
            return [];
        }

        return json_decode(File::get($this->path), true) ?? [];
    }

    protected function save($data)
    {
        File::put($this->path, json_encode($data));
    }

    public function get($key, $default = null)
    {
        $data = $this->load();

        return $data[$key] ?? $default;
    }

    public function set($key, $value)
    {
        $data = $this->load();
        $data[$key] = $value;
        $this->save($data);
    }

    public function forget($key)
    {
        $data = $this->load();
        unset($data[$key]);
        $this->save($data);
    }

    public function all()
    {
        return $this->load();
    }
}
