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
        'coin_symbol',
        'coin_name',
        'coin_watch',
        'coin_price_usd',
        'coin_price_idr',
        'last_analyzed_at',
        'analysis_count',
    ];

     protected $filterable = [
        'coin_code',
        'coin_name',
        'coin_symbol',
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

    /**
     * Check if coin needs analysis (not analyzed in last minute)
     */
    public function needsAnalysis(): bool
    {
        if (!$this->last_analyzed_at) {
            return true;
        }

        return now()->diffInMinutes($this->last_analyzed_at) >= 1;
    }

    /**
     * Update analysis tracking
     */
    public function updateAnalysisTracking(): void
    {
        $this->update([
            'last_analyzed_at' => now(),
            'analysis_count' => ($this->analysis_count ?? 0) + 1,
        ]);
    }

    /**
     * Get coins that need analysis
     */
    public static function getCoinsNeedingAnalysis()
    {
        return static::where('coin_watch', true)
                    ->where(function ($query) {
                        $query->whereNull('last_analyzed_at')
                              ->orWhere('last_analyzed_at', '<', now()->subMinute());
                    })
                    ->get();
    }

}
