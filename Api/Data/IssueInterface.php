<?php
/**
 * Copyright © Performance, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Performance\Review\Api\Data;

/**
 * Performance issue interface
 *
 * @api
 * @since 1.0.0
 */
interface IssueInterface
{
    /**
     * Constants for issue priorities
     */
    const PRIORITY_HIGH = 'High';
    const PRIORITY_MEDIUM = 'Medium';
    const PRIORITY_LOW = 'Low';

    /**
     * Get issue priority
     *
     * @return string
     */
    public function getPriority(): string;

    /**
     * Set issue priority
     *
     * @param string $priority
     * @return $this
     */
    public function setPriority(string $priority): IssueInterface;

    /**
     * Get issue category
     *
     * @return string
     */
    public function getCategory(): string;

    /**
     * Set issue category
     *
     * @param string $category
     * @return $this
     */
    public function setCategory(string $category): IssueInterface;

    /**
     * Get issue description
     *
     * @return string
     */
    public function getIssue(): string;

    /**
     * Set issue description
     *
     * @param string $issue
     * @return $this
     */
    public function setIssue(string $issue): IssueInterface;

    /**
     * Get issue details
     *
     * @return string
     */
    public function getDetails(): string;

    /**
     * Set issue details
     *
     * @param string $details
     * @return $this
     */
    public function setDetails(string $details): IssueInterface;

    /**
     * Get current value
     *
     * @return string|null
     */
    public function getCurrentValue(): ?string;

    /**
     * Set current value
     *
     * @param string|null $value
     * @return $this
     */
    public function setCurrentValue(?string $value): IssueInterface;

    /**
     * Get recommended value
     *
     * @return string|null
     */
    public function getRecommendedValue(): ?string;

    /**
     * Set recommended value
     *
     * @param string|null $value
     * @return $this
     */
    public function setRecommendedValue(?string $value): IssueInterface;
}