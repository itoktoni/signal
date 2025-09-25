<?php

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * @method static static ONE_HOUR()
 * @method static static FOUR_HOURS()
 * @method static static ONE_DAY()
 */
final class TimeIntervalType extends Enum
{
    const ONE_HOUR = '1h';
    const FOUR_HOURS = '4h';
    const ONE_DAY = '1d';

    /**
     * Get the description for the enum value
     */
    public function getIntervalDescription(): string
    {
        return match ($this->value) {
            self::ONE_HOUR => '1 Hour',
            self::FOUR_HOURS => '4 Hours',
            self::ONE_DAY => '1 Day',
        };
    }

    /**
     * Get the display name for the enum value
     */
    public function getDisplayName(): string
    {
        return match ($this->value) {
            self::ONE_HOUR => '1H',
            self::FOUR_HOURS => '4H',
            self::ONE_DAY => '1D',
        };
    }

    /**
     * Get all available time intervals as an array
     */
    public static function getAvailableIntervals(): array
    {
        return [
            self::ONE_HOUR => self::ONE_HOUR()->getIntervalDescription(),
            self::FOUR_HOURS => self::FOUR_HOURS()->getIntervalDescription(),
            self::ONE_DAY => self::ONE_DAY()->getIntervalDescription(),
        ];
    }
}