<?php

namespace App\Traits;

trait OptionModel
{
    /**
     * Get options for model selection
     */
    public static function getOptions(?string $value = null, ?string $key = null): \Illuminate\Support\Collection
    {
        $value = $value ?: static::field_name();
        $key = $key ?: static::field_key();

        return self::pluck($value, $key);
    }
}
