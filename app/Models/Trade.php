<?php

namespace App\Models;

use App\Traits\Filterable;
use App\Traits\ModelHelper;
use App\Traits\DefaultEntity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Trade extends Model
{
    use Filterable, ModelHelper, DefaultEntity;

    protected $fillable = [
        'trade_id',
        'symbol',
        'side',
        'type',
        'amount',
        'price',
        'cost',
        'fee',
        'fee_currency',
        'exchange',
        'exchange_order_id',
        'status',
        'exchange_response',
        'trading_plan_id',
        'analysis_method',
        'analysis_result',
        'entry_price',
        'stop_loss',
        'take_profit',
        'risk_reward_ratio',
        'confidence',
        'notes',
        'executed_at',
        'closed_at',
    ];

    protected $filterable = [
        'symbol',
        'side',
        'type',
        'status',
        'exchange',
        'analysis_method',
    ];

    protected $sortable = [
        'symbol',
        'created_at',
        'executed_at',
        'amount',
        'price',
    ];

    protected $casts = [
        'amount' => 'decimal:8',
        'price' => 'decimal:8',
        'cost' => 'decimal:8',
        'fee' => 'decimal:8',
        'entry_price' => 'decimal:8',
        'stop_loss' => 'decimal:8',
        'take_profit' => 'decimal:8',
        'risk_reward_ratio' => 'decimal:2',
        'confidence' => 'decimal:2',
        'exchange_response' => 'array',
        'analysis_result' => 'array',
        'executed_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public $timestamps = true;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'trades';
    protected $primaryKey = 'trade_id';

    /**
     * Generate unique trade ID
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($trade) {
            if (empty($trade->trade_id)) {
                $trade->trade_id = static::generateTradeId();
            }
        });
    }

    /**
     * Generate a unique trade ID
     */
    public static function generateTradeId()
    {
        do {
            $tradeId = 'TRD-' . strtoupper(Str::random(12));
        } while (static::where('trade_id', $tradeId)->exists());

        return $tradeId;
    }

    public static function field_name()
    {
        return 'trade_id';
    }

    public function getFieldNameAttribute()
    {
        return $this->{$this->field_name()};
    }

    /**
     * Get status badge color for UI
     */
    public function getStatusColorAttribute()
    {
        return match($this->status) {
            'pending' => 'warning',
            'open' => 'info',
            'filled' => 'success',
            'cancelled' => 'secondary',
            'rejected' => 'danger',
            default => 'secondary'
        };
    }

    /**
     * Get status badge text for UI
     */
    public function getStatusTextAttribute()
    {
        return match($this->status) {
            'pending' => 'Pending',
            'open' => 'Open',
            'filled' => 'Filled',
            'cancelled' => 'Cancelled',
            'rejected' => 'Rejected',
            default => ucfirst($this->status)
        };
    }

    /**
     * Check if trade is active (open or filled)
     */
    public function isActive()
    {
        return in_array($this->status, ['open', 'filled']);
    }

    /**
     * Check if trade is completed
     */
    public function isCompleted()
    {
        return in_array($this->status, ['filled', 'cancelled', 'rejected']);
    }

    /**
     * Check if trade is profitable
     */
    public function isProfitable()
    {
        if (!$this->isCompleted() || !$this->price) {
            return null;
        }

        if ($this->side === 'buy') {
            return $this->price > $this->entry_price;
        } else {
            return $this->price < $this->entry_price;
        }
    }

    /**
     * Calculate profit/loss
     */
    public function getPnLAttribute()
    {
        if (!$this->isCompleted() || !$this->price || !$this->entry_price) {
            return null;
        }

        $priceDiff = $this->price - $this->entry_price;

        if ($this->side === 'sell') {
            $priceDiff = -$priceDiff;
        }

        return $priceDiff * $this->amount;
    }

    /**
     * Calculate profit/loss percentage
     */
    public function getPnLPercentageAttribute()
    {
        if (!$this->isCompleted() || !$this->entry_price || $this->entry_price == 0) {
            return null;
        }

        $pnl = $this->pnl;

        if ($pnl === null) {
            return null;
        }

        return ($pnl / ($this->entry_price * $this->amount)) * 100;
    }

    /**
     * Get formatted profit/loss with currency
     */
    public function getFormattedPnLAttribute()
    {
        $pnl = $this->pnl;

        if ($pnl === null) {
            return '-';
        }

        $symbol = $pnl >= 0 ? '+' : '';
        return $symbol . number_format($pnl, 8) . ' ' . ($this->fee_currency ?: 'USD');
    }

    /**
     * Update trade status
     */
    public function updateStatus($status, $exchangeResponse = null)
    {
        $this->status = $status;

        if ($exchangeResponse) {
            $this->exchange_response = $exchangeResponse;
        }

        if ($status === 'filled' && !$this->executed_at) {
            $this->executed_at = now();
        }

        if (in_array($status, ['filled', 'cancelled', 'rejected']) && !$this->closed_at) {
            $this->closed_at = now();
        }

        $this->save();
    }

    /**
     * Get trades by status
     */
    public static function getByStatus($status)
    {
        return static::where('status', $status)->orderBy('created_at', 'desc')->get();
    }

    /**
     * Get active trades
     */
    public static function getActiveTrades()
    {
        return static::whereIn('status', ['open', 'filled'])->orderBy('created_at', 'desc')->get();
    }

    /**
     * Get recent trades
     */
    public static function getRecentTrades($limit = 10)
    {
        return static::orderBy('created_at', 'desc')->limit($limit)->get();
    }

    /**
     * Get trades by symbol
     */
    public static function getBySymbol($symbol)
    {
        return static::where('symbol', $symbol)->orderBy('created_at', 'desc')->get();
    }

    /**
     * Get trades by exchange
     */
    public static function getByExchange($exchange)
    {
        return static::where('exchange', $exchange)->orderBy('created_at', 'desc')->get();
    }

    /**
     * Scope for active trades
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['open', 'filled']);
    }

    /**
     * Scope for completed trades
     */
    public function scopeCompleted($query)
    {
        return $query->whereIn('status', ['filled', 'cancelled', 'rejected']);
    }

    /**
     * Scope for profitable trades
     */
    public function scopeProfitable($query)
    {
        return $query->where('status', 'filled')
                    ->whereRaw('(side = "buy" AND price > entry_price) OR (side = "sell" AND price < entry_price)');
    }

    /**
     * Scope for loss trades
     */
    public function scopeLoss($query)
    {
        return $query->where('status', 'filled')
                    ->whereRaw('(side = "buy" AND price < entry_price) OR (side = "sell" AND price > entry_price)');
    }
}
