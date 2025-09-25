<?php

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * @method static static SNIPER()
 * @method static static DYNAMIC_RR()
 * @method static static SUPPORT_RESISTANCE()
 * @method static static MA_20_50()
 */
final class AnalysisType extends Enum
{
    const SNIPER = 'sniper';
    const DYNAMIC_RR = 'dynamic_rr';
    const SUPPORT_RESISTANCE = 'support_resistance';
    const MA_20_50 = 'ma_20_50';

    /**
     * Get the description for the enum value
     */
    public function getAnalysisDescription(): string
    {
        return match ($this->value) {
            self::SNIPER => 'Sniper Analysis',
            self::DYNAMIC_RR => 'Dynamic Risk-Reward Analysis',
            self::SUPPORT_RESISTANCE => 'Support/Resistance Analysis',
            self::MA_20_50 => 'Moving Average 20/50 Analysis',
        };
    }

}