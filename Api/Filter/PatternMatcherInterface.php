<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */
declare(strict_types=1);

namespace Hryvinskyi\ErrorReporting\Api\Filter;

/**
 * Service for pattern matching operations
 *
 * Handles both string matching and regex pattern matching for filter patterns.
 */
interface PatternMatcherInterface
{
    /**
     * Check if any pattern matches any of the given values
     *
     * @param array<int, string> $patterns Array of patterns to match against
     * @param array<int, string> $values Array of values to check
     * @return bool True if any pattern matches any value
     */
    public function matchesAny(array $patterns, array $values): bool;

    /**
     * Check if a pattern matches any of the given values
     *
     * Supports exact matching, substring matching, and regex patterns.
     *
     * @param string $pattern Pattern to match (can be string or regex)
     * @param array<int, string> $values Array of values to check
     * @return bool True if pattern matches any value
     */
    public function matchesPattern(string $pattern, array $values): bool;

    /**
     * Parse configuration string into array of patterns
     *
     * Supports newline-separated patterns with comment support (# and //).
     * Empty lines and comments are automatically filtered out.
     *
     * @param string $input Configuration string with patterns
     * @return array<int, string> Array of parsed patterns
     */
    public function parsePatterns(string $input): array;
}