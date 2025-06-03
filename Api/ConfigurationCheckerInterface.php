<?php
/**
 * Copyright © Performance, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Performance\Review\Api;

/**
 * Configuration checker interface
 *
 * @api
 * @since 1.0.0
 */
interface ConfigurationCheckerInterface
{
    /**
     * Check configuration for performance issues
     *
     * @return \Performance\Review\Api\Data\IssueInterface[]
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function checkConfiguration(): array;
}