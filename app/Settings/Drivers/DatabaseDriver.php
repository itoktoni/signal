<?php

namespace App\Settings\Drivers;

use Illuminate\Support\Facades\DB;

class DatabaseDriver implements DriverInterface
{
    protected $table;

    public function __construct($config)
    {
        $this->table = $config['table'] ?? 'settings';
    }

    public function get($key, $default = null)
    {
        $row = DB::table($this->table)->where('key', $key)->first();
        return $row ? unserialize($row->value) : $default;
    }

    public function set($key, $value)
    {
        DB::table($this->table)->updateOrInsert(
            ['key' => $key],
            ['value' => serialize($value)]
        );
    }

    public function forget($key)
    {
        DB::table($this->table)->where('key', $key)->delete();
    }

    public function all()
    {
        return DB::table($this->table)->pluck('value', 'key')->map(function ($value) {
            return unserialize($value);
        })->toArray();
    }
}