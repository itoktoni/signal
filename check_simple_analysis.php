<?php
require_once 'vendor/autoload.php';

use App\Analysis\AnalysisServiceFactory;
use App\Settings\Settings;
use App\Settings\Drivers\MemoryDriver;

try {
    // Create a settings instance with MemoryDriver
    $driver = new MemoryDriver([]);
    $settings = new Settings($driver);

    // Create an instance of the analysis service
    $apiManager = new \App\Analysis\ApiProviderManager($settings);
    $analysisService = AnalysisServiceFactory::create('multi_tf_analysis', $apiManager);

    echo "Analysis service class: " . get_class($analysisService) . "\n";
    echo "Analysis service code: " . $analysisService->getCode() . "\n";
    echo "Analysis service name: " . $analysisService->getName() . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}