<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */
declare(strict_types=1);

namespace Hryvinskyi\ErrorReporting\Model\Filter;

use Hryvinskyi\ErrorReporting\Api\Filter\PatternMatcherInterface;

/**
 * Service for pattern matching operations
 *
 * Handles both string matching and regex pattern matching for filter patterns.
 */
class PatternMatcher implements PatternMatcherInterface
{
    /**
     * Check if any pattern matches any of the given values
     *
     * @param array<int, string> $patterns Array of patterns to match against
     * @param array<int, string> $values Array of values to check
     * @return bool True if any pattern matches any value
     */
    public function matchesAny(array $patterns, array $values): bool
    {
        foreach ($patterns as $pattern) {
            if ($this->matchesPattern($pattern, $values)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a pattern matches any of the given values
     *
     * @param string $pattern Pattern to match (can be string or regex)
     * @param array<int, string> $values Array of values to check
     * @return bool True if pattern matches any value
     */
    public function matchesPattern(string $pattern, array $values): bool
    {
        // First try exact match
        if (in_array($pattern, $values, true)) {
            return true;
        }

        // Try regex pattern matching if pattern is valid regex
        if ($this->isValidRegex($pattern)) {
            return $this->matchesRegex($pattern, $values);
        }

        // Try substring matching
        return $this->matchesSubstring($pattern, $values);
    }

    /**
     * Check if pattern matches any value using regex
     *
     * @param string $pattern Regex pattern
     * @param array<int, string> $values Array of values to check
     * @return bool True if regex matches any value
     */
    private function matchesRegex(string $pattern, array $values): bool
    {
        try {
            foreach ($values as $value) {
                if (preg_match($pattern, $value)) {
                    return true;
                }
            }
        } catch (\Exception $e) {
            // Invalid regex, return false
            return false;
        }

        return false;
    }

    /**
     * Check if pattern is found as substring in any value
     *
     * @param string $pattern Pattern to search for
     * @param array<int, string> $values Array of values to check
     * @return bool True if pattern found in any value
     */
    private function matchesSubstring(string $pattern, array $values): bool
    {
        foreach ($values as $value) {
            if (stripos($value, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if string is a valid regex pattern
     *
     * @param string $pattern Pattern to validate
     * @return bool True if valid regex pattern
     */
    private function isValidRegex(string $pattern): bool
    {
        set_error_handler(function () {}, E_WARNING);
        $isValid = preg_match($pattern, '') !== false;
        restore_error_handler();

        return $isValid;
    }

    /**
     * Parse configuration string into array of patterns
     *
     * Supports newline-separated patterns with comment support (# and //)
     *
     * @param string $input Configuration string with patterns
     * @return array<int, string> Array of parsed patterns
     */
    public function parsePatterns(string $input): array
    {
        if (empty($input)) {
            return [];
        }

        $lines = explode("\n", $input);
        $patterns = [];

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip empty lines and comments
            if (empty($line) || str_starts_with($line, '#') || str_starts_with($line, '//')) {
                continue;
            }

            $patterns[] = $line;
        }

        return $patterns;
    }
}