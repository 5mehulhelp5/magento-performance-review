<?php
/**
 * Copyright © Performance, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Performance\Review\Api;

/**
 * Base interface for all performance analyzers
 *
 * @api
 * @since 1.0.0
 */
interface AnalyzerInterface
{
    /**
     * Run analysis and return found issues
     *
     * @return \Performance\Review\Api\Data\IssueInterface[]
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function analyze(): array;

    /**
     * Get analyzer name
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Get analyzer category
     *
     * @return string
     */
    public function getCategory(): string;
}