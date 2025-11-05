<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */
declare(strict_types=1);

namespace Hryvinskyi\ErrorReporting\Model;

use Hryvinskyi\ErrorReporting\Api\ConfigInterface;
use Hryvinskyi\ErrorReporting\Api\ErrorFilterInterface;

/**
 * Service for filtering errors based on configuration
 */
class ErrorFilter implements ErrorFilterInterface
{
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
     * @inheritDoc
     */
    public function shouldReport(\Exception $exception, string $severity, ?int $storeId = null): bool
    {
        // Check if error is blacklisted
        if ($this->isBlacklisted($exception)) {
            return false;
        }

        // Check severity level
        $minSeverity = $this->config->getMinimumSeverityLevel();
        $severityValue = self::SEVERITY_LEVELS[$severity] ?? 2;
        $minSeverityValue = self::SEVERITY_LEVELS[$minSeverity] ?? 2;

        return $severityValue >= $minSeverityValue;
    }

    /**
     * @inheritDoc
     */
    public function isBlacklisted(\Exception $exception, ?int $storeId = null): bool
    {
        $blacklist = $this->config->getErrorBlacklist();
        if (empty($blacklist)) {
            return false;
        }

        $patterns = $this->parseBlacklist($blacklist);
        if (empty($patterns)) {
            return false;
        }

        $exceptionClass = get_class($exception);
        $exceptionMessage = $exception->getMessage();
        $exceptionFile = $exception->getFile();

        foreach ($patterns as $pattern) {
            // Check if pattern matches exception class
            if (stripos($exceptionClass, $pattern) !== false) {
                return true;
            }

            // Check if pattern matches exception message
            if (stripos($exceptionMessage, $pattern) !== false) {
                return true;
            }

            // Check if pattern matches exception file
            if (stripos($exceptionFile, $pattern) !== false) {
                return true;
            }

            // Try regex pattern matching
            if ($this->isValidRegex($pattern)) {
                try {
                    if (preg_match($pattern, $exceptionClass) ||
                        preg_match($pattern, $exceptionMessage) ||
                        preg_match($pattern, $exceptionFile)) {
                        return true;
                    }
                } catch (\Exception $e) {
                    // Invalid regex, ignore
                }
            }
        }

        return false;
    }

    /**
     * Parse blacklist string into array of patterns
     *
     * @param string $blacklist
     * @return array<int, string>
     */
    private function parseBlacklist(string $blacklist): array
    {
        $lines = explode("\n", $blacklist);
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

    /**
     * Check if string is a regex pattern
     *
     * @param string $pattern
     * @return bool
     */
    private function isValidRegex(string $pattern): bool {
        set_error_handler(function() {}, E_WARNING); // suppress warnings temporarily
        $isValid = preg_match($pattern, '') !== false;
        restore_error_handler();
        return $isValid;
    }
}
