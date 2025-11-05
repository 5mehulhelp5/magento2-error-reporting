<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */
declare(strict_types=1);

namespace Hryvinskyi\ErrorReporting\Model;

use Hryvinskyi\ErrorReporting\Api\ConfigInterface;
use Hryvinskyi\ErrorReporting\Api\ConfigStorageInterface;

/**
 * File-based configuration - ZERO database dependencies
 *
 * Reads configuration ONLY from filesystem
 */
class FileConfig implements ConfigInterface
{
    private ?array $config = null;

    /**
     * @param ConfigStorageInterface $configStorage
     */
    public function __construct(
        private readonly ConfigStorageInterface $configStorage
    ) {
    }

    /**
     * @inheritDoc
     */
    public function isEnabled(): bool
    {
        return (bool)$this->getConfigValue('enabled', false);
    }

    /**
     * @inheritDoc
     */
    public function getDeveloperEmails(): string
    {
        return (string)$this->getConfigValue('developer_emails', '');
    }

    /**
     * @inheritDoc
     */
    public function getClientEmails(): string
    {
        return (string)$this->getConfigValue('client_emails', '');
    }

    /**
     * @inheritDoc
     */
    public function isClientNotificationEnabled(): bool
    {
        return (bool)$this->getConfigValue('client_notification_enabled', false);
    }

    /**
     * @inheritDoc
     */
    public function getEmailSender(): string
    {
        return (string)$this->getConfigValue('email_sender', 'general');
    }

    /**
     * @inheritDoc
     */
    public function getErrorBlacklist(): string
    {
        return (string)$this->getConfigValue('error_blacklist', '');
    }

    /**
     * @inheritDoc
     */
    public function getExcludeControllers(): string
    {
        return (string)$this->getConfigValue('excluded_controllers', '');
    }

    /**
     * @inheritDoc
     */
    public function getIncludeOnlyControllers(): string
    {
        return (string)$this->getConfigValue('included_only_controllers', '');
    }

    /**
     * @inheritDoc
     */
    public function getThrottlePeriod(): int
    {
        return (int)$this->getConfigValue('throttle_period', 60);
    }

    /**
     * @inheritDoc
     */
    public function getMaxErrorsPerPeriod(): int
    {
        return (int)$this->getConfigValue('max_errors_per_period', 5);
    }

    /**
     * @inheritDoc
     */
    public function includeDetailedInfo(): bool
    {
        return (bool)$this->getConfigValue('include_detailed_info', true);
    }

    /**
     * @inheritDoc
     */
    public function includePostData(): bool
    {
        return (bool)$this->getConfigValue('include_post_data', false);
    }

    /**
     * @inheritDoc
     */
    public function getMinimumSeverityLevel(): string
    {
        return (string)$this->getConfigValue('minimum_severity', 'error');
    }

    /**
     * Get configuration value from filesystem storage
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    private function getConfigValue(string $key, mixed $default): mixed
    {
        // Load config once and cache in memory
        if ($this->config === null) {
            $this->config = $this->configStorage->getConfig() ?? [];
        }

        return $this->config[$key] ?? $default;
    }
}
