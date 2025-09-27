<?php

namespace App\Traits;

trait DefaultEntity
{
    public static function getModel()
    {
        return new static;
    }

    public static function getTableName()
    {
        return self::getModel()->getTable();
    }

    public static function field_key()
    {
        return self::getModel()->getKeyName();
    }

    public function getFieldKeyAttribute()
    {
        return $this->{$this->field_key()};
    }

    public static function field_primary()
    {
        return self::field_key();
    }

    public function getFieldPrimaryAttribute()
    {
        return $this->{$this->field_primary()};
    }

    public static function field_name()
    {
        return self::field_key();
    }

    public function getFieldNameAttribute()
    {
        return $this->{self::field_name()};
    }
}
