<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */
declare(strict_types=1);

namespace Hryvinskyi\ErrorReporting\Api\Collector;

/**
 * Service for sanitizing sensitive data before reporting
 */
interface DataSanitizerInterface
{
    /**
     * Sanitize array data by removing/masking sensitive fields
     *
     * Recursively processes arrays to find and redact sensitive information
     * based on configurable patterns.
     *
     * @param array<string, mixed> $data Data to sanitize
     * @return array<string, mixed> Sanitized data
     */
    public function sanitize(array $data): array;

    /**
     * Check if a key contains sensitive information
     *
     * @param string $key The key to check
     * @return bool True if key is sensitive
     */
    public function isSensitiveKey(string $key): bool;
}