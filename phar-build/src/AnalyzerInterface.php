<?php
declare(strict_types=1);

namespace Performance\Review\Phar;

interface AnalyzerInterface
{
    public function analyze(string $magentoRoot): array;
}