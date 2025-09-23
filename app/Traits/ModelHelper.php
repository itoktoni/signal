<?php

namespace App\Traits;

trait ModelHelper
{
    /**
     * Get the display field for options
     */
    public static function getDisplayField(): string
    {
        return 'name';
    }

    /**
     * Get options for model selection
     */
    public static function getOptions(?string $valueField = null, ?string $keyField = null): \Illuminate\Support\Collection
    {
        $valueField = $valueField ?: static::getDisplayField();
        $keyField = $keyField ?: (new static)->getKeyName();

        return self::pluck($valueField, $keyField);
    }
}
