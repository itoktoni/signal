<?php

namespace App\Models;

use App\Traits\DefaultEntity;
use App\Traits\Filterable;
use App\Traits\OptionModel;
use Illuminate\Database\Eloquent\Model;

class Menu extends Model
{
    use Filterable, DefaultEntity, OptionModel;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */

    protected $table = 'menu';
    protected $primaryKey = 'menu_code';
    protected $keyType = 'string';

    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'menu_code',
        'menu_group',
        'menu_name',
        'menu_controller',
        'menu_action',
    ];

    protected $filterable = [
        'menu_code',
        'menu_group',
        'menu_name',
        'menu_controller',
    ];

    protected $sortable = [
        'menu_code',
        'menu_group',
        'menu_name',
        'menu_controller',
    ];

    public function rules($id = null)
    {
        $rules = [
            'menu_code' => ['required'],
            'menu_name' => ['required'],
            'menu_group' => ['required'],
        ];

        return $rules;
    }
}
