<?php
/**
 * Copyright © Performance, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Performance\Review\Model;

use Performance\Review\Api\Data\IssueInterface;
use Magento\Framework\DataObject;

/**
 * Performance issue model
 *
 * @since 1.0.0
 */
class Issue extends DataObject implements IssueInterface
{
    /**
     * @inheritdoc
     */
    public function getPriority(): string
    {
        return (string) $this->getData('priority');
    }

    /**
     * @inheritdoc
     */
    public function setPriority(string $priority): IssueInterface
    {
        return $this->setData('priority', $priority);
    }

    /**
     * @inheritdoc
     */
    public function getCategory(): string
    {
        return (string) $this->getData('category');
    }

    /**
     * @inheritdoc
     */
    public function setCategory(string $category): IssueInterface
    {
        return $this->setData('category', $category);
    }

    /**
     * @inheritdoc
     */
    public function getIssue(): string
    {
        return (string) $this->getData('issue');
    }

    /**
     * @inheritdoc
     */
    public function setIssue(string $issue): IssueInterface
    {
        return $this->setData('issue', $issue);
    }

    /**
     * @inheritdoc
     */
    public function getDetails(): string
    {
        return (string) $this->getData('details');
    }

    /**
     * @inheritdoc
     */
    public function setDetails(string $details): IssueInterface
    {
        return $this->setData('details', $details);
    }

    /**
     * @inheritdoc
     */
    public function getCurrentValue(): ?string
    {
        return $this->getData('current_value');
    }

    /**
     * @inheritdoc
     */
    public function setCurrentValue(?string $value): IssueInterface
    {
        return $this->setData('current_value', $value);
    }

    /**
     * @inheritdoc
     */
    public function getRecommendedValue(): ?string
    {
        return $this->getData('recommended_value');
    }

    /**
     * @inheritdoc
     */
    public function setRecommendedValue(?string $value): IssueInterface
    {
        return $this->setData('recommended_value', $value);
    }
}