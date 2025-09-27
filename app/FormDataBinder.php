<?php

namespace App;

class FormDataBinder
{
    /**
     * The currently bound model.
     */
    private static $currentModel = null;

    /**
     * Bind a target to the current instance
     *
     * @param  mixed  $target
     */
    public static function bind($target): void
    {
        self::$currentModel = $target;
    }

    /**
     * Get the currently bound target.
     *
     * @return mixed
     */
    public static function get()
    {
        return self::$currentModel;
    }

    /**
     * Remove the current binding.
     */
    public static function pop(): void
    {
        self::$currentModel = null;
    }
}