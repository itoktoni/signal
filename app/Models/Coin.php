<?php

namespace App\Models;

use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Model;

class Coin extends Model
{
    use Filterable;

    protected $fillable = [
        'coin_id',
        'coin_symbol',
        'coin_base',
        'coin_asset',
    ];

     protected $filterable = [
        'coin_code',
    ];

    public $timestamps = true;
    protected $table = 'coin';
    protected $primaryKey = 'coin_id';

}
