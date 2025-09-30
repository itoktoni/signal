<?php

namespace App\Models;

use App\Traits\DefaultEntity;
use App\Traits\Filterable;
use App\Traits\ModelHelper;
use Illuminate\Database\Eloquent\Model;

class SymbolApi extends Model
{
    use Filterable, ModelHelper, DefaultEntity;

    protected $fillable = [
        'symbol_api_id',
        'symbol_api_coin',
        'symbol_api_code',
        'symbol_api_name',
        'symbol_api_provider',
    ];

     protected $filterable = [
        'symbol_api_coin',
        'symbol_api_code',
        'symbol_api_name',
        'symbol_api_provider',
    ];

    protected $sortable = [
        'symbol_api_coin',
        'symbol_api_code',
        'symbol_api_name',
        'symbol_api_provider',
    ];

    public $timestamps = true;
    public $incrementing = true;
    // protected $keyType = 'string';

    protected $table = 'symbol_api';
    protected $primaryKey = 'symbol_api_id';

    public static function field_name()
    {
        return 'symbol_api_name';
    }

    public function getFieldNameAttribute()
    {
        return $this->{$this->field_name()};
    }

}
