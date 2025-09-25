<?php

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * @method static static SNIPER()
 * @method static static DYNAMIC_RR()
 */
final class AnalysisType extends Enum
{
    const SNIPER = 'sniper';
    const DYNAMIC_RR = 'dynamic_rr';

    /**
     * Get the description for the enum value
     */
    public function getAnalysisDescription(): string
    {
        return match ($this->value) {
            self::SNIPER => 'Sniper Analysis',
            self::DYNAMIC_RR => 'Dynamic Risk-Reward Analysis',
        };
    }

    /**
     * Get the detailed description for the enum value
     */
    public function getDetailedDescription(): string
    {
        return match ($this->value) {
            self::SNIPER => 'High precision entry signals based on volume and price action',
            self::DYNAMIC_RR => 'Dynamic risk-reward calculation using ATR, Fibonacci levels, and support/resistance',
        };
    }

    /**
     * Get the display name for the enum value
     */
    public function getDisplayName(): string
    {
        return match ($this->value) {
            self::SNIPER => 'Sniper',
            self::DYNAMIC_RR => 'RR Dinamis',
        };
    }

    /**
     * Get all available analysis types as an array
     */
    public static function getAvailableTypes(): array
    {
        return [
            self::SNIPER => self::SNIPER()->getAnalysisDescription(),
            self::DYNAMIC_RR => self::DYNAMIC_RR()->getAnalysisDescription(),
        ];
    }

    /**
     * Get all analysis types with their detailed descriptions
     */
    public static function getTypesWithDescriptions(): array
    {
        return [
            self::SNIPER => [
                'name' => self::SNIPER()->getDisplayName(),
                'description' => self::SNIPER()->getAnalysisDescription(),
                'detailed' => self::SNIPER()->getDetailedDescription(),
            ],
            self::DYNAMIC_RR => [
                'name' => self::DYNAMIC_RR()->getDisplayName(),
                'description' => self::DYNAMIC_RR()->getAnalysisDescription(),
                'detailed' => self::DYNAMIC_RR()->getDetailedDescription(),
            ],
        ];
    }
}