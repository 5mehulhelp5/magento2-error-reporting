<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */
declare(strict_types=1);

namespace Hryvinskyi\ErrorReporting\Api\Collector;

/**
 * Service for determining error severity levels
 */
interface SeverityResolverInterface
{
    /**
     * Determine error severity level based on exception type
     *
     * @param \Throwable $exception
     * @return string One of: 'critical', 'error', 'warning'
     */
    public function resolve(\Throwable $exception): string;
}