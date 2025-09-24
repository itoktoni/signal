<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CryptoSymbol extends Model
{
    protected $fillable = [
        'symbol',
        'base_asset',
        'quote_asset',
        'status',
        'is_spot_trading_allowed',
        'is_margin_trading_allowed',
        'min_price',
        'max_price',
        'tick_size',
        'min_qty',
        'max_qty',
        'step_size',
        'filters',
        'last_fetched_at',
    ];

    protected $casts = [
        'is_spot_trading_allowed' => 'boolean',
        'is_margin_trading_allowed' => 'boolean',
        'min_price' => 'decimal:10',
        'max_price' => 'decimal:10',
        'tick_size' => 'decimal:10',
        'min_qty' => 'decimal:10',
        'max_qty' => 'decimal:10',
        'step_size' => 'decimal:10',
        'filters' => 'array',
        'last_fetched_at' => 'datetime',
    ];

    /**
     * Scope for active trading symbols
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'TRADING')
                    ->where('is_spot_trading_allowed', true);
    }

    /**
     * Scope for USDT pairs
     */
    public function scopeUsdtPairs($query)
    {
        return $query->where('quote_asset', 'USDT');
    }

    /**
     * Scope for BTC pairs
     */
    public function scopeBtcPairs($query)
    {
        return $query->where('quote_asset', 'BTC');
    }

    /**
     * Get formatted symbol for display
     */
    public function getFormattedSymbolAttribute()
    {
        return $this->symbol;
    }

    /**
     * Check if symbol is available for trading
     */
    public function isAvailableForTrading()
    {
        return $this->status === 'TRADING' && $this->is_spot_trading_allowed;
    }
}
