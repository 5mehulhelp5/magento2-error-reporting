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
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Configuration model for error reporting module
 *
 * Reads from database first, falls back to filesystem if database is unavailable
 */
class MagentoConfig implements ConfigInterface
{
    private const XML_PATH_ENABLED = 'hryvinskyi_error_reporting/general/enabled';

    // Email configuration paths
    private const XML_PATH_EMAIL_ENABLED = 'hryvinskyi_error_reporting/email/enabled';
    private const XML_PATH_EMAIL_MINIMUM_SEVERITY = 'hryvinskyi_error_reporting/email/minimum_severity';
    private const XML_PATH_DEVELOPER_EMAILS = 'hryvinskyi_error_reporting/email/developer_emails';
    private const XML_PATH_CLIENT_EMAILS = 'hryvinskyi_error_reporting/email/client_emails';
    private const XML_PATH_CLIENT_NOTIFICATION_ENABLED = 'hryvinskyi_error_reporting/email/client_notification_enabled';
    private const XML_PATH_EMAIL_SENDER = 'hryvinskyi_error_reporting/email/email_sender';

    // Filtering configuration paths
    private const XML_PATH_ERROR_BLACKLIST = 'hryvinskyi_error_reporting/filtering/error_blacklist';
    private const XML_PATH_FILTERING_EXCLUDE_CONTROLLERS = 'hryvinskyi_error_reporting/filtering/exclude_controllers';
    private const XML_PATH_FILTERING_INCLUDE_ONLY_CONTROLLERS = 'hryvinskyi_error_reporting/filtering/include_only_controllers';

    // Throttling configuration paths
    private const XML_PATH_THROTTLE_PERIOD = 'hryvinskyi_error_reporting/throttling/period_minutes';
    private const XML_PATH_MAX_ERRORS_PER_PERIOD = 'hryvinskyi_error_reporting/throttling/max_errors_per_period';

    // Details configuration paths
    private const XML_PATH_INCLUDE_DETAILED_INFO = 'hryvinskyi_error_reporting/details/include_detailed_info';
    private const XML_PATH_INCLUDE_POST_DATA = 'hryvinskyi_error_reporting/details/include_post_data';

    // Slack configuration paths
    private const XML_PATH_SLACK_ENABLED = 'hryvinskyi_error_reporting/slack/enabled';
    private const XML_PATH_SLACK_WEBHOOK_URL = 'hryvinskyi_error_reporting/slack/webhook_url';
    private const XML_PATH_SLACK_CHANNEL = 'hryvinskyi_error_reporting/slack/channel';
    private const XML_PATH_SLACK_USERNAME = 'hryvinskyi_error_reporting/slack/username';
    private const XML_PATH_SLACK_MIN_SEVERITY = 'hryvinskyi_error_reporting/slack/minimum_severity';

    // Microsoft Teams configuration paths
    private const XML_PATH_TEAMS_ENABLED = 'hryvinskyi_error_reporting/teams/enabled';
    private const XML_PATH_TEAMS_WEBHOOK_URL = 'hryvinskyi_error_reporting/teams/webhook_url';
    private const XML_PATH_TEAMS_MIN_SEVERITY = 'hryvinskyi_error_reporting/teams/minimum_severity';

    // Telegram configuration paths
    private const XML_PATH_TELEGRAM_ENABLED = 'hryvinskyi_error_reporting/telegram/enabled';
    private const XML_PATH_TELEGRAM_BOT_TOKEN = 'hryvinskyi_error_reporting/telegram/bot_token';
    private const XML_PATH_TELEGRAM_CHAT_ID = 'hryvinskyi_error_reporting/telegram/chat_id';
    private const XML_PATH_TELEGRAM_MIN_SEVERITY = 'hryvinskyi_error_reporting/telegram/minimum_severity';

    // WhatsApp configuration paths
    private const XML_PATH_WHATSAPP_ENABLED = 'hryvinskyi_error_reporting/whatsapp/enabled';
    private const XML_PATH_WHATSAPP_ACCESS_TOKEN = 'hryvinskyi_error_reporting/whatsapp/access_token';
    private const XML_PATH_WHATSAPP_PHONE_NUMBER_ID = 'hryvinskyi_error_reporting/whatsapp/phone_number_id';
    private const XML_PATH_WHATSAPP_RECIPIENT_PHONE = 'hryvinskyi_error_reporting/whatsapp/recipient_phone';
    private const XML_PATH_WHATSAPP_MIN_SEVERITY = 'hryvinskyi_error_reporting/whatsapp/minimum_severity';

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * @inheritDoc
     */
    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLED,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @inheritDoc
     */
    public function isEmailEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_EMAIL_ENABLED,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @inheritDoc
     */
    public function getEmailMinimumSeverity(): string
    {
        $value = (string)$this->scopeConfig->getValue(
            self::XML_PATH_EMAIL_MINIMUM_SEVERITY,
            ScopeInterface::SCOPE_STORE
        );
        return $value ?: 'error';
    }

    /**
     * @inheritDoc
     */
    public function getDeveloperEmails(): string
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_DEVELOPER_EMAILS,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @inheritDoc
     */
    public function getClientEmails(): string
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_CLIENT_EMAILS,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @inheritDoc
     */
    public function isClientNotificationEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_CLIENT_NOTIFICATION_ENABLED,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @inheritDoc
     */
    public function getEmailSender(): string
    {
        $value = (string)$this->scopeConfig->getValue(
            self::XML_PATH_EMAIL_SENDER,
            ScopeInterface::SCOPE_STORE
        );
        return $value ?: 'general';
    }

    /**
     * @inheritDoc
     */
    public function getErrorBlacklist(): string
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_ERROR_BLACKLIST,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @inheritDoc
     */
    public function getExcludeControllers(): string
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_FILTERING_EXCLUDE_CONTROLLERS,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @inheritDoc
     */
    public function getIncludeOnlyControllers(): string
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_FILTERING_INCLUDE_ONLY_CONTROLLERS,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @inheritDoc
     */
    public function getThrottlePeriod(): int
    {
        $value = (int)$this->scopeConfig->getValue(
            self::XML_PATH_THROTTLE_PERIOD,
            ScopeInterface::SCOPE_STORE
        );
        return $value ?: 60;
    }

    /**
     * @inheritDoc
     */
    public function getMaxErrorsPerPeriod(): int
    {
        $value = (int)$this->scopeConfig->getValue(
            self::XML_PATH_MAX_ERRORS_PER_PERIOD,
            ScopeInterface::SCOPE_STORE
        );
        return $value ?: 5;
    }

    /**
     * @inheritDoc
     */
    public function includeDetailedInfo(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_INCLUDE_DETAILED_INFO,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @inheritDoc
     */
    public function includePostData(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_INCLUDE_POST_DATA,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @inheritDoc
     */
    public function isSlackEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_SLACK_ENABLED,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @inheritDoc
     */
    public function getSlackWebhookUrl(): string
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_SLACK_WEBHOOK_URL,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @inheritDoc
     */
    public function getSlackChannel(): string
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_SLACK_CHANNEL,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @inheritDoc
     */
    public function getSlackUsername(): string
    {
        $value = (string)$this->scopeConfig->getValue(
            self::XML_PATH_SLACK_USERNAME,
            ScopeInterface::SCOPE_STORE
        );
        return $value ?: 'Magento Error Reporter';
    }

    /**
     * @inheritDoc
     */
    public function getSlackMinimumSeverity(): string
    {
        $value = (string)$this->scopeConfig->getValue(
            self::XML_PATH_SLACK_MIN_SEVERITY,
            ScopeInterface::SCOPE_STORE
        );
        return $value ?: 'critical';
    }

    /**
     * @inheritDoc
     */
    public function isTeamsEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_TEAMS_ENABLED,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @inheritDoc
     */
    public function getTeamsWebhookUrl(): string
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_TEAMS_WEBHOOK_URL,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @inheritDoc
     */
    public function getTeamsMinimumSeverity(): string
    {
        $value = (string)$this->scopeConfig->getValue(
            self::XML_PATH_TEAMS_MIN_SEVERITY,
            ScopeInterface::SCOPE_STORE
        );
        return $value ?: 'critical';
    }

    /**
     * @inheritDoc
     */
    public function isTelegramEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_TELEGRAM_ENABLED,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @inheritDoc
     */
    public function getTelegramBotToken(): string
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_TELEGRAM_BOT_TOKEN,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @inheritDoc
     */
    public function getTelegramChatId(): string
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_TELEGRAM_CHAT_ID,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @inheritDoc
     */
    public function getTelegramMinimumSeverity(): string
    {
        $value = (string)$this->scopeConfig->getValue(
            self::XML_PATH_TELEGRAM_MIN_SEVERITY,
            ScopeInterface::SCOPE_STORE
        );
        return $value ?: 'critical';
    }

    /**
     * @inheritDoc
     */
    public function isWhatsAppEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_WHATSAPP_ENABLED,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @inheritDoc
     */
    public function getWhatsAppAccessToken(): string
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_WHATSAPP_ACCESS_TOKEN,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @inheritDoc
     */
    public function getWhatsAppPhoneNumberId(): string
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_WHATSAPP_PHONE_NUMBER_ID,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @inheritDoc
     */
    public function getWhatsAppRecipientPhone(): string
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_WHATSAPP_RECIPIENT_PHONE,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @inheritDoc
     */
    public function getWhatsAppMinimumSeverity(): string
    {
        $value = (string)$this->scopeConfig->getValue(
            self::XML_PATH_WHATSAPP_MIN_SEVERITY,
            ScopeInterface::SCOPE_STORE
        );
        return $value ?: 'critical';
    }
}
