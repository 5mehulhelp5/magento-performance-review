<?php
/**
 * Copyright © Performance, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Performance\Review\Model;

use Magento\Framework\ObjectManagerInterface;
use Performance\Review\Api\Data\IssueInterface;

/**
 * Factory for creating Issue instances
 *
 * @since 1.0.0
 */
class IssueFactory
{
    /**
     * @var ObjectManagerInterface
     */
    private ObjectManagerInterface $objectManager;

    /**
     * @var string
     */
    private string $instanceName;

    /**
     * Constructor
     *
     * @param ObjectManagerInterface $objectManager
     * @param string $instanceName
     */
    public function __construct(
        ObjectManagerInterface $objectManager,
        string $instanceName = Issue::class
    ) {
        $this->objectManager = $objectManager;
        $this->instanceName = $instanceName;
    }

    /**
     * Create new Issue instance
     *
     * @param array $data
     * @return IssueInterface
     */
    public function create(array $data = []): IssueInterface
    {
        return $this->objectManager->create($this->instanceName, ['data' => $data]);
    }
}