<?php
declare(strict_types=1);

namespace Performance\Review\Phar;

class Issue implements IssueInterface
{
    private string $priority;
    private string $recommendation;
    private string $details;

    public function __construct(string $priority, string $recommendation, string $details)
    {
        $this->priority = $priority;
        $this->recommendation = $recommendation;
        $this->details = $details;
    }

    public function getPriority(): string
    {
        return $this->priority;
    }

    public function getRecommendation(): string
    {
        return $this->recommendation;
    }

    public function getDetails(): string
    {
        return $this->details;
    }
}