<?php

namespace App\Analysis;

use App\Analysis\Contract\MarketDataInterface;
use App\Analysis\Contract\AnalysisAbstract;
use App\Enums\AnalysisType;
use Illuminate\Support\Facades\Log;

class AnalysisServiceFactory
{
    /**
     * Cache for discovered analysis classes
     */
    private static array $analysisClasses = [];

    /**
     * Get all available analysis classes
     */
    private static function discoverAnalysisClasses(): array
    {
        if (!empty(self::$analysisClasses)) {
            return self::$analysisClasses;
        }

        // Hardcoded for now since auto-discovery is complex
        self::$analysisClasses = [
            'keltner_channel' => [
                'class' => 'App\\Analysis\\DefaultAnalysis',
                'name' => 'Default Analysis',
                'code' => 'keltner_channel'
            ]
        ];

        Log::info('Analysis classes discovered', [
            'classes' => self::$analysisClasses,
            'available_methods' => array_keys(self::$analysisClasses)
        ]);

        return self::$analysisClasses;
    }


    /**
     * Get list of available analysis methods for select dropdown
     */
    public static function getAvailableMethods(): array
    {
        $classes = self::discoverAnalysisClasses();
        $methods = [];

        foreach ($classes as $code => $info) {
            $methods[$code] = $info['name'];
        }

        return $methods;
    }

    /**
     * Create analysis service instance by method code
     */
    public static function createAnalysis(string $methodCode, $provider): AnalysisAbstract
    {
        $classes = self::discoverAnalysisClasses();

        if (!isset($classes[$methodCode])) {
            Log::error("Analysis method not found", [
                'method_code' => $methodCode,
                'available_methods' => array_keys($classes),
                'provider' => is_object($provider) ? get_class($provider) : 'unknown'
            ]);
            throw new \Exception("Analysis method '{$methodCode}' not found");
        }

        $className = $classes[$methodCode]['class'];

        Log::info("Creating analysis instance", [
            'method_code' => $methodCode,
            'class_name' => $className,
            'provider_class' => get_class($provider)
        ]);

        // Create instance with provider dependency injection
        return new $className($provider);
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