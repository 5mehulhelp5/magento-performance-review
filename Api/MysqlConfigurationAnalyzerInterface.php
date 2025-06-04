<?php
/**
 * Copyright © Performance, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Performance\Review\Api;

/**
 * Interface for MySQL configuration analyzer
 *
 * @api
 * @since 1.0.0
 */
interface MysqlConfigurationAnalyzerInterface extends AnalyzerInterface
{
    /**
     * Analyze MySQL configuration for performance issues
     *
     * @return \Performance\Review\Api\Data\IssueInterface[]
     */
    public function analyzeMysqlConfiguration(): array;
}