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
     * Check if error reporting is enabled globally
     *
     * @return bool
     */
    public function isEnabled(): bool;

    /**
     * Check if email notifications are enabled
     *
     * @return bool
     */
    public function isEmailEnabled(): bool;

    /**
     * Get minimum severity level for email notifications
     *
     * @return string (critical|error|warning)
     */
    public function getEmailMinimumSeverity(): string;

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
     * Check if Slack notifications are enabled
     *
     * @return bool
     */
    public function isSlackEnabled(): bool;

    /**
     * Get Slack webhook URL
     *
     * @return string
     */
    public function getSlackWebhookUrl(): string;

    /**
     * Get Slack channel name (optional)
     *
     * @return string
     */
    public function getSlackChannel(): string;

    /**
     * Get Slack username for bot
     *
     * @return string
     */
    public function getSlackUsername(): string;

    /**
     * Get minimum severity level for Slack notifications
     *
     * @return string (critical|error|warning)
     */
    public function getSlackMinimumSeverity(): string;

    /**
     * Check if Microsoft Teams notifications are enabled
     *
     * @return bool
     */
    public function isTeamsEnabled(): bool;

    /**
     * Get Microsoft Teams webhook URL
     *
     * @return string
     */
    public function getTeamsWebhookUrl(): string;

    /**
     * Get minimum severity level for Teams notifications
     *
     * @return string (critical|error|warning)
     */
    public function getTeamsMinimumSeverity(): string;

    /**
     * Check if Telegram notifications are enabled
     *
     * @return bool
     */
    public function isTelegramEnabled(): bool;

    /**
     * Get Telegram bot token
     *
     * @return string
     */
    public function getTelegramBotToken(): string;

    /**
     * Get Telegram chat ID
     *
     * @return string
     */
    public function getTelegramChatId(): string;

    /**
     * Get minimum severity level for Telegram notifications
     *
     * @return string (critical|error|warning)
     */
    public function getTelegramMinimumSeverity(): string;

    /**
     * Check if WhatsApp notifications are enabled
     *
     * @return bool
     */
    public function isWhatsAppEnabled(): bool;

    /**
     * Get WhatsApp access token
     *
     * @return string
     */
    public function getWhatsAppAccessToken(): string;

    /**
     * Get WhatsApp phone number ID
     *
     * @return string
     */
    public function getWhatsAppPhoneNumberId(): string;

    /**
     * Get WhatsApp recipient phone number
     *
     * @return string
     */
    public function getWhatsAppRecipientPhone(): string;

    /**
     * Get minimum severity level for WhatsApp notifications
     *
     * @return string (critical|error|warning)
     */
    public function getWhatsAppMinimumSeverity(): string;
}
