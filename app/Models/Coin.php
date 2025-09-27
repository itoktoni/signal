<?php

namespace App\Models;

use App\Traits\Filterable;
use App\Traits\ModelHelper;
use Illuminate\Database\Eloquent\Model;

class Coin extends Model
{
    use Filterable, ModelHelper;

    protected $fillable = [
        'coin_code',
        'coin_watch',
        'coin_price_usd',
        'coin_price_idr',
    ];

     protected $filterable = [
        'coin_code',
        'coin_watch',
    ];

    protected $sortable = [
        'coin_code',
    ];

    public $timestamps = true;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'coin';
    protected $primaryKey = 'coin_code';

    public static function field_name()
    {
        return 'coin_code';
    }

    public function getFieldNameAttribute()
    {
        return $this->{$this->field_name()};
    }

}
