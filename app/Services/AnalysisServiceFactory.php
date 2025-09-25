<?php

namespace App\Services;

use App\Services\AnalysisInterface;
use InvalidArgumentException;

class AnalysisServiceFactory
{
    /**
     * Create an analysis service instance based on the method name
     */
    public static function create(string $method): AnalysisInterface
    {
        return match ($method) {
            'sniper' => new SniperService(),
            'support_resistance' => new SupportResistanceService(),
            'dynamic_rr' => new DynamicRRService(),
            default => throw new InvalidArgumentException("Unknown analysis method: {$method}")
        };
    }

    /**
     * Get list of available analysis methods
     */
    public static function getAvailableMethods(): array
    {
        return [
            'sniper' => 'Sniper Analysis',
            'support_resistance' => 'Support/Resistance Analysis',
            'dynamic_rr' => 'Dynamic Risk-Reward Analysis'
        ];
    }

    /**
     * Get method description
     */
    public static function getMethodDescription(string $method): string
    {
        $descriptions = [
            'sniper' => 'High precision entry signals based on volume and price action',
            'support_resistance' => 'Classic support and resistance level analysis',
            'dynamic_rr' => 'Dynamic risk-reward calculation using ATR, Fibonacci levels, and support/resistance'
        ];

        return $descriptions[$method] ?? 'Unknown method';
    }
}