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
use Magento\Framework\App\Request\Http as RequestHttp;

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
    public function shouldReport(\Throwable $exception, RequestHttp $request, string $severity): bool
    {
        // Check if error is blacklisted
        if ($this->isBlacklistedException($exception)) {
            return false;
        }

        // Check if error reporting is enabled for controllers
        if ($this->isBlacklistedController($request)) {
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
    public function isBlacklistedException(\Throwable $exception): bool
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
     * Check if controller is blacklisted based on configuration
     *
     * @param RequestHttp $request
     * @return bool
     */
    public function isBlacklistedController(RequestHttp $request): bool
    {
        $slash = trim($request->getFullActionName('/'), '/');
        $underscore = trim($request->getFullActionName('_'), '_');
        $url = $request->getRequestUri();

        $targets = array_filter([$slash, $underscore]);

        // Load include-only (whitelist) and exclude lists from config
        $includeOnlyRaw = $this->config->getIncludeOnlyControllers();
        $excludeRaw = $this->config->getExcludeControllers();

        $includes = $this->parseBlacklist($includeOnlyRaw);
        $excludes = $this->parseBlacklist($excludeRaw);

        // If include-only is present, then everything not matching it should be considered blacklisted
        if (!empty($includes)) {
            $matched = false;
            foreach ($includes as $pattern) {
                if (in_array($pattern, $targets, true)) {
                    $matched = true;
                    break;
                }

                // Check if pattern matches URL
                if (stripos($url, $pattern) !== false) {
                    $matched = true;
                    break;
                }

                if ($this->isValidRegex($pattern)) {
                    try {
                        foreach ($targets as $t) {
                            if (preg_match($pattern, $t)) {
                                $matched = true;
                                break 2;
                            }
                        }
                    } catch (\Exception $e) {
                        // ignore invalid regex
                    }
                } else {
                    foreach ($targets as $t) {
                        if (stripos($t, $pattern) !== false) {
                            $matched = true;
                            break 2;
                        }
                    }
                }
            }

            if (!$matched) {
                // Not in whitelist -> treat as blacklisted
                return true;
            }
        }

        // Check excludes; if any exclude matches, treat as blacklisted
        if (!empty($excludes)) {
            foreach ($excludes as $pattern) {
                if (in_array($pattern, $targets, true)) {
                    return true;
                }

                // Check if pattern matches URL
                if (stripos($url, $pattern) !== false) {
                    return true;
                }

                if ($this->isValidRegex($pattern)) {
                    try {
                        foreach ($targets as $t) {
                            if (preg_match($pattern, $t)) {
                                return true;
                            }
                        }
                    } catch (\Exception $e) {
                        // ignore invalid regex
                    }
                } else {
                    foreach ($targets as $t) {
                        if (stripos($t, $pattern) !== false) {
                            return true;
                        }
                    }
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
