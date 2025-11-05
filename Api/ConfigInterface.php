<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */
declare(strict_types=1);

namespace Hryvinskyi\ErrorReporting\Api;

/**
 * Configuration interface for error reporting module
 */
interface ConfigInterface
{
    /**
     * Check if error reporting is enabled
     *
     * @return bool
     */
    public function isEnabled(): bool;

    /**
     * Get developer email addresses (comma-separated)
     *
     * @return string
     */
    public function getDeveloperEmails(): string;

    /**
     * Get client email addresses (comma-separated)
     *
     * @return string
     */
    public function getClientEmails(): string;

    /**
     * Check if client notifications are enabled
     *
     * @return bool
     */
    public function isClientNotificationEnabled(): bool;

    /**
     * Get email sender identity
     *
     * @return string
     */
    public function getEmailSender(): string;

    /**
     * Get blacklisted error patterns (one per line)
     *
     * @return string
     */
    public function getErrorBlacklist(): string;

    /**
     * Get excluded controllers (one per line)
     *
     * @return string
     */
    public function getExcludeControllers(): string;

    /**
     * Get included controllers (one per line)
     *
     * @return string
     */
    public function getIncludeOnlyControllers(): string;

    /**
     * Get throttle period in minutes
     *
     * @return int
     */
    public function getThrottlePeriod(): int;

    /**
     * Get maximum errors per throttle period
     *
     * @return int
     */
    public function getMaxErrorsPerPeriod(): int;

    /**
     * Check if detailed information should be included
     *
     * @return bool
     */
    public function includeDetailedInfo(): bool;

    /**
     * Check if POST data should be included
     *
     * @return bool
     */
    public function includePostData(): bool;

    /**
     * Get error severity level for notifications (critical, error, warning)
     *
     * @return string
     */
    public function getMinimumSeverityLevel(): string;
}
