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
    public function isEmailEnabled(): bool
    {
        return (bool)$this->getConfigValue('email_enabled', false);
    }

    /**
     * @inheritDoc
     */
    public function getEmailMinimumSeverity(): string
    {
        return (string)$this->getConfigValue('email_minimum_severity', 'error');
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
    public function isSlackEnabled(): bool
    {
        return (bool)$this->getConfigValue('slack_enabled', false);
    }

    /**
     * @inheritDoc
     */
    public function getSlackWebhookUrl(): string
    {
        return (string)$this->getConfigValue('slack_webhook_url', '');
    }

    /**
     * @inheritDoc
     */
    public function getSlackChannel(): string
    {
        return (string)$this->getConfigValue('slack_channel', '');
    }

    /**
     * @inheritDoc
     */
    public function getSlackUsername(): string
    {
        return (string)$this->getConfigValue('slack_username', 'Magento Error Reporter');
    }

    /**
     * @inheritDoc
     */
    public function getSlackMinimumSeverity(): string
    {
        return (string)$this->getConfigValue('slack_minimum_severity', 'critical');
    }

    /**
     * @inheritDoc
     */
    public function isTeamsEnabled(): bool
    {
        return (bool)$this->getConfigValue('teams_enabled', false);
    }

    /**
     * @inheritDoc
     */
    public function getTeamsWebhookUrl(): string
    {
        return (string)$this->getConfigValue('teams_webhook_url', '');
    }

    /**
     * @inheritDoc
     */
    public function getTeamsMinimumSeverity(): string
    {
        return (string)$this->getConfigValue('teams_minimum_severity', 'critical');
    }

    /**
     * @inheritDoc
     */
    public function isTelegramEnabled(): bool
    {
        return (bool)$this->getConfigValue('telegram_enabled', false);
    }

    /**
     * @inheritDoc
     */
    public function getTelegramBotToken(): string
    {
        return (string)$this->getConfigValue('telegram_bot_token', '');
    }

    /**
     * @inheritDoc
     */
    public function getTelegramChatId(): string
    {
        return (string)$this->getConfigValue('telegram_chat_id', '');
    }

    /**
     * @inheritDoc
     */
    public function getTelegramMinimumSeverity(): string
    {
        return (string)$this->getConfigValue('telegram_minimum_severity', 'critical');
    }

    /**
     * @inheritDoc
     */
    public function isWhatsAppEnabled(): bool
    {
        return (bool)$this->getConfigValue('whatsapp_enabled', false);
    }

    /**
     * @inheritDoc
     */
    public function getWhatsAppAccessToken(): string
    {
        return (string)$this->getConfigValue('whatsapp_access_token', '');
    }

    /**
     * @inheritDoc
     */
    public function getWhatsAppPhoneNumberId(): string
    {
        return (string)$this->getConfigValue('whatsapp_phone_number_id', '');
    }

    /**
     * @inheritDoc
     */
    public function getWhatsAppRecipientPhone(): string
    {
        return (string)$this->getConfigValue('whatsapp_recipient_phone', '');
    }

    /**
     * @inheritDoc
     */
    public function getWhatsAppMinimumSeverity(): string
    {
        return (string)$this->getConfigValue('whatsapp_minimum_severity', 'critical');
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
