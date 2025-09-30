<?php

namespace App\Analysis;

use App\Enums\AnalysisType;

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
                    if ($reflection->implementsInterface(AnalysisInterface::class) && !$reflection->isAbstract()) {
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
     * Create an analysis service instance based on the method name
     */
    public static function create(string $method, ?ApiProviderManager $apiManager = null): AnalysisInterface
    {
        $classes = self::discoverAnalysisClasses();

        if (isset($classes[$method])) {
            $className = $classes[$method]['class'];

            $instance = new $className();

            // If ApiProviderManager is provided, inject the API provider (prefer Binance)
            if ($apiManager && method_exists($instance, 'setApiProvider')) {
                // Try Binance first (higher priority), fallback to CoinGecko
                $provider = $apiManager->getProvider('binance') ?? $apiManager->getProvider('coingecko');
                if ($provider) {
                    $instance->setApiProvider($provider);
                }
            }

            return $instance;
        }

        // Default to first available analysis or MA Analysis
        $defaultClass = $classes[array_key_first($classes)]['class'] ?? \App\Analysis\DefaultAnalysis::class;

        $instance = new $defaultClass();

        // If ApiProviderManager is provided, inject the API provider (prefer Binance)
        if ($apiManager && method_exists($instance, 'setApiProvider')) {
            // Try Binance first (higher priority), fallback to CoinGecko
            $provider = $apiManager->getProvider('binance') ?? $apiManager->getProvider('coingecko');
            if ($provider) {
                $instance->setApiProvider($provider);
            }
        }

        return $instance;
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