<?php
namespace App\Traits;

trait OptionEnum
{
    /**
     * Get options for model selection
     */
    public static function getOptions($selected = null): \Illuminate\Support\Collection
    {
        $collect = collect(self::getInstances());

        if ($selected && is_array($selected)) {
            $collect = $collect->whereIn('value', $selected);
        } elseif ($selected && is_int($selected)) {
            $collect = $collect->where('value', $selected);
        }

        $data = [];
        foreach ($collect as $item) {
            $data[$item->value] = $item->description;
        }

        return collect($data);
    }
}
