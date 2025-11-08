<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */
declare(strict_types=1);

namespace Hryvinskyi\ErrorReporting\Api;

/**
 * Interface for configuration storage
 */
interface ConfigStorageInterface
{
    /**
     * Export configuration
     *
     * @return array{
     *     enabled: bool,
     *     error_blacklist: string,
     *     excluded_controllers: string,
     *     included_only_controllers: string,
     *     throttle_period: int,
     *     max_errors_per_period: int,
     *     include_detailed_info: bool,
     *     include_post_data: bool,
     *     email_enabled: bool,
     *     email_minimum_severity: string,
     *     developer_emails: string,
     *     client_notification_enabled: bool,
     *     client_emails: string,
     *     email_sender: string,
     *     slack_enabled: bool,
     *     slack_webhook_url: string,
     *     slack_channel: string,
     *     slack_username: string,
     *     slack_minimum_severity: string,
     *     teams_enabled: bool,
     *     teams_webhook_url: string,
     *     teams_minimum_severity: string
     * }|null
     */
    public function exportConfig(): ?array;

    /**
     * Save configuration
     *
     * @param array{
     *     enabled: bool,
     *     error_blacklist: string,
     *     excluded_controllers: string,
     *     included_only_controllers: string,
     *     throttle_period: int,
     *     max_errors_per_period: int,
     *     include_detailed_info: bool,
     *     include_post_data: bool,
     *     email_enabled: bool,
     *     email_minimum_severity: string,
     *     developer_emails: string,
     *     client_notification_enabled: bool,
     *     client_emails: string,
     *     email_sender: string,
     *     slack_enabled: bool,
     *     slack_webhook_url: string,
     *     slack_channel: string,
     *     slack_username: string,
     *     slack_minimum_severity: string,
     *     teams_enabled: bool,
     *     teams_webhook_url: string,
     *     teams_minimum_severity: string
     * } $config
     * @return bool
     */
    public function saveConfig(array $config): bool;

    /**
     * Get configuration from storage
     *
     * @return array{
     *     enabled: bool,
     *     error_blacklist: string,
     *     excluded_controllers: string,
     *     included_only_controllers: string,
     *     throttle_period: int,
     *     max_errors_per_period: int,
     *     include_detailed_info: bool,
     *     include_post_data: bool,
     *     email_enabled: bool,
     *     email_minimum_severity: string,
     *     developer_emails: string,
     *     client_notification_enabled: bool,
     *     client_emails: string,
     *     email_sender: string,
     *     slack_enabled: bool,
     *     slack_webhook_url: string,
     *     slack_channel: string,
     *     slack_username: string,
     *     slack_minimum_severity: string,
     *     teams_enabled: bool,
     *     teams_webhook_url: string,
     *     teams_minimum_severity: string,
     *     exported_at?: int
     * }|null
     */
    public function getConfig(): ?array;

    /**
     * Check if configuration exists in storage
     *
     * @return bool
     */
    public function hasConfig(): bool;
}
