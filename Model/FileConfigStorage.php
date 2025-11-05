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
    public function exportConfig(): bool
    {
        try {
            $config = [
                'enabled' => $this->sourceConfig->isEnabled(),
                'developer_emails' => $this->sourceConfig->getDeveloperEmails(),
                'client_notification_enabled' => $this->sourceConfig->isClientNotificationEnabled(),
                'client_emails' => $this->sourceConfig->getClientEmails(),
                'email_sender' => $this->sourceConfig->getEmailSender(),
                'error_blacklist' => $this->sourceConfig->getErrorBlacklist(),
                'throttle_period' => $this->sourceConfig->getThrottlePeriod(),
                'max_errors_per_period' => $this->sourceConfig->getMaxErrorsPerPeriod(),
                'include_detailed_info' => $this->sourceConfig->includeDetailedInfo(),
                'include_post_data' => $this->sourceConfig->includePostData(),
                'minimum_severity' => $this->sourceConfig->getMinimumSeverityLevel(),
                'exported_at' => time(),
            ];

            return $this->writeConfig($config);
        } catch (\Exception $e) {
            $this->logger->error('Failed to export error reporting configuration from database', [
                'error' => $e->getMessage()
            ]);
            return false;
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
     * @param array<string, mixed> $config
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
