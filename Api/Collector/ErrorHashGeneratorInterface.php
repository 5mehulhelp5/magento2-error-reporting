<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */
declare(strict_types=1);

namespace Hryvinskyi\ErrorReporting\Api\Collector;

/**
 * Service for generating unique error hashes for grouping
 */
interface ErrorHashGeneratorInterface
{
    /**
     * Generate unique hash for error grouping
     *
     * Same errors (same class, message, file, line) will produce the same hash.
     *
     * @param \Throwable $exception
     * @return string SHA-256 hash
     */
    public function generate(\Throwable $exception): string;
}