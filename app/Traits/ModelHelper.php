<?php

namespace App\Traits;

trait ModelHelper
{
    /**
     * Get the display field for options
     *
     * @return string
     */
    public static function getDisplayField(): string
    {
        return 'name';
    }

    /**
     * Get options for model selection
     *
     * @param string|null $valueField
     * @param string|null $keyField
     * @return \Illuminate\Support\Collection
     */
    public static function getOptions(?string $valueField = null, ?string $keyField = null): \Illuminate\Support\Collection
    {
        $valueField = $valueField ?: static::getDisplayField();
        $keyField = $keyField ?: (new static())->getKeyName();

        return self::pluck($valueField, $keyField);
    }
}