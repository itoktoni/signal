<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TradingPlan extends Model
{
    protected $fillable = [
        'symbol',
        'position_type',
        'entry_price',
        'stop_loss',
        'take_profit',
        'risk_reward_ratio',
        'success_rate',
        'position_size',
        'costs',
        'net_rr',
        'timeframe',
        'status',
        'analysis_data',
        'user_id',
    ];

    protected $casts = [
        'entry_price' => 'decimal:8',
        'stop_loss' => 'decimal:8',
        'take_profit' => 'decimal:8',
        'risk_reward_ratio' => 'decimal:2',
        'success_rate' => 'decimal:2',
        'position_size' => 'decimal:8',
        'costs' => 'decimal:4',
        'net_rr' => 'decimal:2',
        'analysis_data' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getRiskAmountAttribute(): float
    {
        return abs($this->entry_price - $this->stop_loss) * $this->position_size;
    }

    public function getPotentialProfitAttribute(): float
    {
        if ($this->position_type === 'long') {
            return ($this->take_profit - $this->entry_price) * $this->position_size;
        } else {
            return ($this->entry_price - $this->take_profit) * $this->position_size;
        }
    }

    public function getNetProfitAttribute(): float
    {
        return $this->potential_profit - $this->costs;
    }

    public function calculateNetRR(): float
    {
        $risk = $this->risk_amount;
        if ($risk == 0) return 0;

        $reward = $this->potential_profit;
        $feeImpact = $this->costs / $this->position_size; // Fee per unit

        return round(($reward / $risk) * (1 - $feeImpact), 2);
    }

    public function updateNetRR(): void
    {
        $this->net_rr = $this->calculateNetRR();
        $this->save();
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeBySymbol($query, $symbol)
    {
        return $query->where('symbol', strtoupper($symbol));
    }

    public function scopeByPositionType($query, $type)
    {
        return $query->where('position_type', $type);
    }
}
