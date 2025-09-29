<?php
require_once 'vendor/autoload.php';

use App\Analysis\AnalysisServiceFactory;

try {
    $methods = AnalysisServiceFactory::getAvailableMethods();
    echo "Available analysis methods:\n";
    print_r($methods);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}