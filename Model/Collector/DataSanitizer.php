<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */
declare(strict_types=1);

namespace Hryvinskyi\ErrorReporting\Model\Collector;

use Hryvinskyi\ErrorReporting\Api\Collector\DataSanitizerInterface;
use Hryvinskyi\ErrorReporting\Api\ConfigInterface;

/**
 * Service for sanitizing sensitive data before reporting
 *
 * Masks sensitive information like passwords, credit cards, API keys, etc.
 * Field patterns are configurable via system configuration and di.xml.
 */
class DataSanitizer implements DataSanitizerInterface
{
    /**
     * Default sensitive field patterns
     */
    private const DEFAULT_SENSITIVE_PATTERNS = [
        'password',
        'passwd',
        'pwd',
        'secret',
        'token',
        'api_key',
        'apikey',
        'access_token',
        'refresh_token',
        'private_key',
        'cc_number',
        'cc_cid',
        'cc_cvv',
        'cvv',
        'card_number',
        'card_cvv',
        'ssn',
        'social_security',
    ];

    /**
     * Replacement text for redacted values
     */
    private const REDACTED_TEXT = '***REDACTED***';

    /**
     * Cached sensitive patterns (merged from config and defaults)
     *
     * @var array<int, string>|null
     */
    private ?array $cachedPatterns = null;

    /**
     * @param ConfigInterface $config
     * @param array<int, string> $additionalPatterns Additional patterns from di.xml
     */
    public function __construct(
        private readonly ConfigInterface $config,
        private readonly array $additionalPatterns = []
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function sanitize(array $data): array
    {
        foreach ($data as $key => $value) {
            // Check if this key contains sensitive information
            if ($this->isSensitiveKey((string)$key)) {
                $data[$key] = self::REDACTED_TEXT;
                continue;
            }

            // Recursively sanitize nested arrays
            if (is_array($value)) {
                $data[$key] = $this->sanitize($value);
            }
        }

        return $data;
    }

    /**
     * {@inheritDoc}
     */
    public function isSensitiveKey(string $key): bool
    {
        $lowerKey = strtolower($key);
        $patterns = $this->getSensitivePatterns();

        foreach ($patterns as $pattern) {
            if (str_contains($lowerKey, strtolower($pattern))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get all sensitive field patterns (merged from all sources)
     *
     * Sources (in order of priority):
     * 1. System configuration (from admin panel)
     * 2. Additional patterns from di.xml
     * 3. Default built-in patterns
     *
     * @return array<int, string>
     */
    private function getSensitivePatterns(): array
    {
        // Return cached patterns if available
        if ($this->cachedPatterns !== null) {
            return $this->cachedPatterns;
        }

        $patterns = self::DEFAULT_SENSITIVE_PATTERNS;

        // Add patterns from di.xml
        if (!empty($this->additionalPatterns)) {
            $patterns = array_merge($patterns, $this->additionalPatterns);
        }

        // Add patterns from system configuration
        $configPatterns = $this->getConfigPatterns();
        if (!empty($configPatterns)) {
            $patterns = array_merge($patterns, $configPatterns);
        }

        // Remove duplicates and cache
        $this->cachedPatterns = array_unique($patterns);

        return $this->cachedPatterns;
    }

    /**
     * Get sensitive patterns from system configuration
     *
     * Expects comma-separated or newline-separated list of patterns.
     *
     * @return array<int, string>
     */
    private function getConfigPatterns(): array
    {
        // TODO: Add new config method to ConfigInterface: getSensitiveDataPatterns()
        // For now, return empty array
        // Once config method exists, parse it like this:

        // Example implementation when config is ready:
        // $configValue = $this->config->getSensitiveDataPatterns();
        // if (empty($configValue)) {
        //     return [];
        // }
        //
        // // Split by comma or newline
        // $patterns = preg_split('/[,\n\r]+/', $configValue, -1, PREG_SPLIT_NO_EMPTY);
        // return array_map('trim', $patterns);

        return [];
    }
}