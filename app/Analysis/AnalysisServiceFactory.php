<?php

namespace App\Analysis;

use App\Analysis\AnalysisInterface;
use App\Enums\AnalysisType;
use InvalidArgumentException;

class AnalysisServiceFactory
{
    /**
     * Create an analysis service instance based on the method name
     */
    public static function create(string $method): AnalysisInterface
    {
        return match ($method) {
            AnalysisType::SNIPER => new SniperService(),
            AnalysisType::DYNAMIC_RR => new DynamicRRService(),
            default => throw new InvalidArgumentException("Unknown analysis method: {$method}")
        };
    }

    /**
     * Get list of available analysis methods
     */
    public static function getAvailableMethods(): array
    {
        return AnalysisType::getAvailableTypes();
    }

    /**
     * Get method description
     */
    public static function getMethodDescription(string $method): string
    {
        try {
            $analysisType = AnalysisType::fromValue($method);
            return $analysisType->getDetailedDescription();
        } catch (\Exception $e) {
            return 'Unknown method';
        }
    }
}