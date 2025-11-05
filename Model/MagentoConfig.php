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
    private const XML_PATH_DEVELOPER_EMAILS = 'hryvinskyi_error_reporting/general/developer_emails';
    private const XML_PATH_CLIENT_EMAILS = 'hryvinskyi_error_reporting/general/client_emails';
    private const XML_PATH_CLIENT_NOTIFICATION_ENABLED = 'hryvinskyi_error_reporting/general/client_notification_enabled';
    private const XML_PATH_EMAIL_SENDER = 'hryvinskyi_error_reporting/general/email_sender';
    private const XML_PATH_ERROR_BLACKLIST = 'hryvinskyi_error_reporting/filtering/error_blacklist';
    private const XML_PATH_THROTTLE_PERIOD = 'hryvinskyi_error_reporting/throttling/period_minutes';
    private const XML_PATH_MAX_ERRORS_PER_PERIOD = 'hryvinskyi_error_reporting/throttling/max_errors_per_period';
    private const XML_PATH_INCLUDE_DETAILED_INFO = 'hryvinskyi_error_reporting/details/include_detailed_info';
    private const XML_PATH_INCLUDE_POST_DATA = 'hryvinskyi_error_reporting/details/include_post_data';
    private const XML_PATH_MINIMUM_SEVERITY = 'hryvinskyi_error_reporting/filtering/minimum_severity';

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
    public function getMinimumSeverityLevel(): string
    {
        $value = (string)$this->scopeConfig->getValue(
            self::XML_PATH_MINIMUM_SEVERITY,
            ScopeInterface::SCOPE_STORE
        );
        return $value ?: 'error';
    }
}
