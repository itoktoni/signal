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
     * Discover all analysis classes in the app/Analysis directory
     */
    private static function discoverAnalysisClasses(): array
    {
        if (!empty(self::$analysisClasses)) {
            return self::$analysisClasses;
        }

        // Construct path manually since Laravel helpers may not be available
        $analysisPath = __DIR__; // This is app/Analysis directory
        $files = glob($analysisPath . '/*.php');

        foreach ($files as $file) {
            $className = basename($file, '.php');
            $fullClassName = 'App\\Analysis\\' . $className;

            // Skip interfaces and abstract classes
            if ($className === 'AnalysisInterface' || $className === 'AnalysisService') {
                continue;
            }

            // Check if class exists and implements AnalysisInterface
            if (class_exists($fullClassName)) {
                try {
                    $reflection = new \ReflectionClass($fullClassName);
                    if ($reflection->implementsInterface(MarketDataInterface::class) && !$reflection->isAbstract()) {
                        $instance = $reflection->newInstanceWithoutConstructor();
                        self::$analysisClasses[$instance->getCode()] = [
                            'class' => $fullClassName,
                            'name' => $instance->getName(),
                            'code' => $instance->getCode()
                        ];
                    }
                } catch (\Exception $e) {
                    // Skip classes that can't be instantiated without constructor
                    continue;
                }
            }
        }

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
