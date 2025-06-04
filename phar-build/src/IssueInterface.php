<?php
declare(strict_types=1);

namespace Performance\Review\Phar;

interface IssueInterface
{
    const PRIORITY_HIGH = 'high';
    const PRIORITY_MEDIUM = 'medium';
    const PRIORITY_LOW = 'low';

    public function getPriority(): string;
    public function getRecommendation(): string;
    public function getDetails(): string;
}