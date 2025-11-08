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
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

/**
 * Filesystem-based configuration storage for error reporting
 *
 * Simple file-based storage - no unnecessary abstractions
 */
class FileConfigStorage implements ConfigStorageInterface
{
    private const CONFIG_FILE = 'error_reporting_config.json';
    private ?WriteInterface $varDirectory = null;

    public function __construct(
        private readonly ConfigInterface $sourceConfig,
        private readonly Filesystem $filesystem,
        private readonly Json $json,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function exportConfig(): ?array
    {
        try {
            return [
                // General settings
                'enabled' => $this->sourceConfig->isEnabled(),

                // Filtering settings
                'error_blacklist' => $this->sourceConfig->getErrorBlacklist(),
                'excluded_controllers' => $this->sourceConfig->getExcludeControllers(),
                'included_only_controllers' => $this->sourceConfig->getIncludeOnlyControllers(),

                // Throttling settings
                'throttle_period' => $this->sourceConfig->getThrottlePeriod(),
                'max_errors_per_period' => $this->sourceConfig->getMaxErrorsPerPeriod(),

                // Detail settings
                'include_detailed_info' => $this->sourceConfig->includeDetailedInfo(),
                'include_post_data' => $this->sourceConfig->includePostData(),

                // Email notification settings
                'email_enabled' => $this->sourceConfig->isEmailEnabled(),
                'email_minimum_severity' => $this->sourceConfig->getEmailMinimumSeverity(),
                'developer_emails' => $this->sourceConfig->getDeveloperEmails(),
                'client_notification_enabled' => $this->sourceConfig->isClientNotificationEnabled(),
                'client_emails' => $this->sourceConfig->getClientEmails(),
                'email_sender' => $this->sourceConfig->getEmailSender(),

                // Slack notification settings
                'slack_enabled' => $this->sourceConfig->isSlackEnabled(),
                'slack_webhook_url' => $this->sourceConfig->getSlackWebhookUrl(),
                'slack_channel' => $this->sourceConfig->getSlackChannel(),
                'slack_username' => $this->sourceConfig->getSlackUsername(),
                'slack_minimum_severity' => $this->sourceConfig->getSlackMinimumSeverity(),

                // Microsoft Teams notification settings
                'teams_enabled' => $this->sourceConfig->isTeamsEnabled(),
                'teams_webhook_url' => $this->sourceConfig->getTeamsWebhookUrl(),
                'teams_minimum_severity' => $this->sourceConfig->getTeamsMinimumSeverity(),

                // Telegram notification settings
                'telegram_enabled' => $this->sourceConfig->isTelegramEnabled(),
                'telegram_bot_token' => $this->sourceConfig->getTelegramBotToken(),
                'telegram_chat_id' => $this->sourceConfig->getTelegramChatId(),
                'telegram_minimum_severity' => $this->sourceConfig->getTelegramMinimumSeverity(),

                // WhatsApp notification settings
                'whatsapp_enabled' => $this->sourceConfig->isWhatsAppEnabled(),
                'whatsapp_access_token' => $this->sourceConfig->getWhatsAppAccessToken(),
                'whatsapp_phone_number_id' => $this->sourceConfig->getWhatsAppPhoneNumberId(),
                'whatsapp_recipient_phone' => $this->sourceConfig->getWhatsAppRecipientPhone(),
                'whatsapp_minimum_severity' => $this->sourceConfig->getWhatsAppMinimumSeverity(),
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to export error reporting configuration from database', [
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }

    /**
     * @inheritDoc
     */
    public function saveConfig(array $config): bool
    {
        $config['exported_at'] = time();
        return $this->writeConfig($config);
    }

    /**
     * Write configuration to filesystem
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
     *     teams_minimum_severity: string,
     *     telegram_enabled: bool,
     *     telegram_bot_token: string,
     *     telegram_chat_id: string,
     *     telegram_minimum_severity: string,
     *     whatsapp_enabled: bool,
     *     whatsapp_access_token: string,
     *     whatsapp_phone_number_id: string,
     *     whatsapp_recipient_phone: string,
     *     whatsapp_minimum_severity: string,
     *     exported_at: int
     * } $config
     * @return bool
     */
    private function writeConfig(array $config): bool
    {
        try {
            $directory = $this->getVarDirectory();
            $content = $this->json->serialize($config);
            $directory->writeFile(self::CONFIG_FILE, $content);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to write error reporting configuration to filesystem', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * @inheritDoc
     */
    public function getConfig(): ?array
    {
        try {
            $directory = $this->getVarDirectory();

            if (!$directory->isExist(self::CONFIG_FILE)) {
                return null;
            }

            $content = $directory->readFile(self::CONFIG_FILE);
            return $this->json->unserialize($content);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * @inheritDoc
     */
    public function hasConfig(): bool
    {
        try {
            return $this->getVarDirectory()->isExist(self::CONFIG_FILE);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get var directory for writing
     *
     * @return WriteInterface
     * @throws FileSystemException
     */
    private function getVarDirectory(): WriteInterface
    {
        if ($this->varDirectory === null) {
            $this->varDirectory = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
        }

        return $this->varDirectory;
    }
}
