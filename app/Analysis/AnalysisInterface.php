<?php

namespace App\Analysis;

interface AnalysisInterface
{
    public function analyze(string $symbol, float $amount = 1000): object;
    public function getName(): string;
}