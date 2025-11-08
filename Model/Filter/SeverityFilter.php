<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */
declare(strict_types=1);

namespace Hryvinskyi\ErrorReporting\Model\Filter;

use Hryvinskyi\ErrorReporting\Api\Filter\SeverityFilterInterface;

/**
 * Centralized severity checking service for notification handlers
 *
 * Provides severity level comparison logic used across all notification handlers.
 * Follows Single Responsibility Principle - only handles severity comparison.
 */
class SeverityFilter implements SeverityFilterInterface
{
    /**
     * Severity level mapping to numeric values for comparison
     *
     * @var array<string, int>
     */
    private const SEVERITY_LEVELS = [
        'warning' => 1,
        'error' => 2,
        'critical' => 3,
    ];

    /**
     * {@inheritDoc}
     */
    public function meetsMinimumSeverity(string $errorSeverity, string $minSeverity): bool
    {
        $errorLevel = self::SEVERITY_LEVELS[$errorSeverity] ?? self::SEVERITY_LEVELS['error'];
        $minLevel = self::SEVERITY_LEVELS[$minSeverity] ?? self::SEVERITY_LEVELS['error'];

        return $errorLevel >= $minLevel;
    }
}