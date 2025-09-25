<?php

namespace App\Analysis;

use App\Analysis\AnalysisInterface;
use App\Analysis\MaAnalysis;
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
            AnalysisType::MA_20_50 => new MaAnalysis(),
            default => new MaAnalysis() // Default to MA Analysis only
        };
    }

    /**
     * Get list of available analysis methods
     */
    public static function getAvailableMethods(): array
    {
        return [
            AnalysisType::MA_20_50 => AnalysisType::MA_20_50()->getAnalysisDescription(),
        ];
    }

    /**
     * Get method description
     */
    public static function getMethodDescription(string $method): string
    {
        try {
            $analysisType = AnalysisType::fromValue($method);
            return $analysisType->getAnalysisDescription();
        } catch (\Exception $e) {
            return 'Unknown method';
        }
    }
}