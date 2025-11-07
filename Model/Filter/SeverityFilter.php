<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */
declare(strict_types=1);

namespace Hryvinskyi\ErrorReporting\Model\Filter;

use Hryvinskyi\ErrorReporting\Api\ConfigInterface;
use Hryvinskyi\ErrorReporting\Api\Data\FilterContextInterface;
use Hryvinskyi\ErrorReporting\Api\Filter\FilterInterface;

/**
 * Filter errors based on severity level configuration
 *
 * Filters out errors below the configured minimum severity level.
 */
class SeverityFilter implements FilterInterface
{
    /**
     * Severity level mapping to numeric values for comparison
     */
    private const SEVERITY_LEVELS = [
        'warning' => 1,
        'error' => 2,
        'critical' => 3,
    ];

    /**
     * @param ConfigInterface $config
     */
    public function __construct(
        private readonly ConfigInterface $config
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function shouldFilter(FilterContextInterface $context): bool
    {
        $severity = $context->getSeverity();
        $minSeverity = $this->config->getMinimumSeverityLevel();

        $severityValue = self::SEVERITY_LEVELS[$severity] ?? self::SEVERITY_LEVELS['error'];
        $minSeverityValue = self::SEVERITY_LEVELS[$minSeverity] ?? self::SEVERITY_LEVELS['error'];

        // Filter out if severity is below minimum level
        return $severityValue < $minSeverityValue;
    }
}