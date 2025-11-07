<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */
declare(strict_types=1);

namespace Hryvinskyi\ErrorReporting\Model\Collector;

use Hryvinskyi\ErrorReporting\Api\Collector\ErrorHashGeneratorInterface;

/**
 * Service for generating unique error hashes for grouping
 *
 * Uses SHA-256 hash of exception class, message, file, and line number.
 */
class ErrorHashGenerator implements ErrorHashGeneratorInterface
{
    /**
     * {@inheritDoc}
     */
    public function generate(\Throwable $exception): string
    {
        // Create hash from exception class, message, file, and line
        // Same errors will have the same hash for grouping
        $hashData = sprintf(
            '%s:%s:%s:%d',
            get_class($exception),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        );

        return hash('sha256', $hashData);
    }
}