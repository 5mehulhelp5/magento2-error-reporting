<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */
declare(strict_types=1);

namespace Hryvinskyi\ErrorReporting\Api\Filter;

/**
 * Interface for severity checking service
 *
 * Provides severity level comparison functionality for notification handlers.
 */
interface SeverityFilterInterface
{
    /**
     * Check if error severity meets or exceeds minimum threshold
     *
     * This method is used by notification handlers to determine if they should
     * handle an error based on its severity level compared to the configured minimum.
     *
     * @param string $errorSeverity Current error severity level (warning|error|critical)
     * @param string $minSeverity Minimum required severity level (warning|error|critical)
     * @return bool True if error severity meets or exceeds minimum, false otherwise
     */
    public function meetsMinimumSeverity(string $errorSeverity, string $minSeverity): bool;
}
