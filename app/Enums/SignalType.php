<?php

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * @method static static LONG()
 * @method static static SHORT()
 * @method static static NEUTRAL()
 */
final class SignalType extends Enum
{
    const LONG = 'long';
    const SHORT = 'short';
    const NEUTRAL = 'neutral';

    /**
     * Get the description for the enum value
     */
    public function getSignalDescription(): string
    {
        return match ($this->value) {
            self::LONG => 'Long Position',
            self::SHORT => 'Short Position',
            self::NEUTRAL => 'Neutral Position',
        };
    }

    /**
     * Get the display name for the enum value
     */
    public function getDisplayName(): string
    {
        return match ($this->value) {
            self::LONG => 'Long',
            self::SHORT => 'Short',
            self::NEUTRAL => 'Neutral',
        };
    }

    /**
     * Get the color class for the signal
     */
    public function getColorClass(): string
    {
        return match ($this->value) {
            self::LONG => 'success',
            self::SHORT => 'danger',
            self::NEUTRAL => 'warning',
        };
    }

    /**
     * Get all available signals as an array
     */
    public static function getAvailableSignals(): array
    {
        return [
            self::LONG => self::LONG()->getSignalDescription(),
            self::SHORT => self::SHORT()->getSignalDescription(),
            self::NEUTRAL => self::NEUTRAL()->getSignalDescription(),
        ];
    }

    /**
     * Get signals with their display names and colors
     */
    public static function getSignalsWithDetails(): array
    {
        return [
            self::LONG => [
                'name' => self::LONG()->getDisplayName(),
                'description' => self::LONG()->getSignalDescription(),
                'color' => self::LONG()->getColorClass(),
            ],
            self::SHORT => [
                'name' => self::SHORT()->getDisplayName(),
                'description' => self::SHORT()->getSignalDescription(),
                'color' => self::SHORT()->getColorClass(),
            ],
            self::NEUTRAL => [
                'name' => self::NEUTRAL()->getDisplayName(),
                'description' => self::NEUTRAL()->getSignalDescription(),
                'color' => self::NEUTRAL()->getColorClass(),
            ],
        ];
    }
}