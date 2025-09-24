<?php

namespace App\Traits;

trait ModelHelper
{
    /**
     * Get the display field for options
     */
    public static function field_name(): string
    {
        return 'name';
    }

    /**
     * Get options for model selection
     */
    public static function getOptions(?string $valueField = null, ?string $keyField = null): \Illuminate\Support\Collection
    {
        $valueField = $valueField ?: static::field_name();
        $keyField = $keyField ?: (new static)->getKeyName();

        return self::pluck($valueField, $keyField);
    }
}
