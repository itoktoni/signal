<?php

namespace App\Models;

use App\Traits\DefaultEntity;
use App\Traits\Filterable;
use App\Traits\ModelHelper;
use Illuminate\Database\Eloquent\Model;

class Symbol extends Model
{
    use Filterable, ModelHelper, DefaultEntity;

    protected $fillable = [
        'symbol_id',
        'symbol_coin',
        'symbol_code',
        'symbol_name',
        'symbol_provider',
    ];

     protected $filterable = [
        'symbol_coin',
        'symbol_code',
        'symbol_name',
        'symbol_provider',
    ];

    protected $sortable = [
        'symbol_coin',
        'symbol_code',
        'symbol_name',
        'symbol_provider',
    ];

    public $timestamps = false;
    public $incrementing = true;
    // protected $keyType = 'string';

    protected $table = 'symbol';
    protected $primaryKey = 'symbol_id';

    public static function field_name()
    {
        return 'symbol_name';
    }

    public function getFieldNameAttribute()
    {
        return $this->{$this->field_name()};
    }

}
