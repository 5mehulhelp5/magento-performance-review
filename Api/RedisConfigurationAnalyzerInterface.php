<?php
/**
 * Copyright © Performance, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Performance\Review\Api;

/**
 * Interface for Redis configuration analyzer
 *
 * @api
 * @since 1.0.0
 */
interface RedisConfigurationAnalyzerInterface extends AnalyzerInterface
{
    /**
     * Analyze Redis configuration for performance issues
     *
     * @return \Performance\Review\Api\Data\IssueInterface[]
     */
    public function analyzeRedisConfiguration(): array;
}