<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */
declare(strict_types=1);

namespace Hryvinskyi\ErrorReporting\Model;

use Hryvinskyi\ErrorReporting\Api\ConfigInterface;
use Hryvinskyi\ErrorReporting\Api\ThrottleServiceInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * Service for throttling error notifications to prevent spam (filesystem-based)
 */
class ThrottleService implements ThrottleServiceInterface
{
    private const ERROR_TRACKING_DIR = 'error_reporting';
    private const TRACKING_FILE_PREFIX = 'error_';

    private ?WriteInterface $varDirectory = null;

    /**
     * @param ConfigInterface $config
     * @param Filesystem $filesystem
     * @param Json $json
     */
    public function __construct(
        private readonly ConfigInterface $config,
        private readonly Filesystem $filesystem,
        private readonly Json $json
    ) {
    }

    /**
     * @inheritDoc
     */
    public function shouldNotify(string $errorHash, ?int $storeId = null): bool
    {
        try {
            $trackingData = $this->getTrackingData($errorHash);

            if (empty($trackingData)) {
                // New error, should notify
                return true;
            }

            $now = time();
            $throttlePeriod = $this->config->getThrottlePeriod() * 60; // Convert minutes to seconds
            $lastNotified = $trackingData['last_notified_at'] ?? 0;

            if (($now - $lastNotified) < $throttlePeriod) {
                // Within throttle period, don't notify
                return false;
            }

            // Check if max notifications per period exceeded
            $maxErrors = $this->config->getMaxErrorsPerPeriod();
            $notificationCount = $trackingData['notification_count'] ?? 0;

            if ($notificationCount >= $maxErrors) {
                // Check if enough time has passed to reset the counter
                $periodHours = $this->config->getThrottlePeriod() / 60; // Convert to hours
                $hoursPassedSinceFirst = ($now - $lastNotified) / 3600;

                if ($hoursPassedSinceFirst < $periodHours) {
                    return false;
                }
            }

            return true;
        } catch (\Exception $e) {
            // If file operations fail, allow notification (fail-safe)
            return true;
        }
    }

    /**
     * @inheritDoc
     */
    public function recordError(string $errorHash, array $errorData, ?int $storeId = null): void
    {
        try {
            $trackingData = $this->getTrackingData($errorHash);
            $now = time();

            $error = $errorData['error'] ?? [];
            $request = $errorData['request'] ?? [];

            if (empty($trackingData)) {
                // New error
                $trackingData = [
                    'error_hash' => $errorHash,
                    'error_type' => $error['type'] ?? 'Unknown',
                    'error_message' => mb_substr($error['message'] ?? '', 0, 1000),
                    'error_file' => $error['file'] ?? '',
                    'error_line' => $error['line'] ?? 0,
                    'severity' => $error['severity'] ?? 'error',
                    'store_id' => $storeId,
                    'url' => $request['url'] ?? null,
                    'occurrence_count' => 1,
                    'first_seen_at' => $now,
                    'last_seen_at' => $now,
                    'notification_count' => 0,
                    'last_notified_at' => null,
                ];
            } else {
                // Update existing error
                $trackingData['occurrence_count'] = ($trackingData['occurrence_count'] ?? 0) + 1;
                $trackingData['last_seen_at'] = $now;
                $trackingData['url'] = $request['url'] ?? $trackingData['url'];
            }

            $this->saveTrackingData($errorHash, $trackingData);
        } catch (\Exception $e) {
            // Silently fail - don't break error reporting if tracking fails
        }
    }

    /**
     * @inheritDoc
     */
    public function markAsNotified(string $errorHash): void
    {
        try {
            $trackingData = $this->getTrackingData($errorHash);

            if (!empty($trackingData)) {
                $trackingData['last_notified_at'] = time();
                $trackingData['notification_count'] = ($trackingData['notification_count'] ?? 0) + 1;

                $this->saveTrackingData($errorHash, $trackingData);
            }
        } catch (\Exception $e) {
            // Silently fail
        }
    }

    /**
     * Get tracking data for error hash
     *
     * @param string $errorHash
     * @return array{
     *     error_hash: string,
     *     error_type: string,
     *     error_message: string,
     *     error_file: string,
     *     error_line: int,
     *     severity: string,
     *     store_id: int|null,
     *     url: string|null,
     *     occurrence_count: int,
     *     first_seen_at: int,
     *     last_seen_at: int,
     *     notification_count: int,
     *     last_notified_at: int|null
     * }|array<empty, empty>
     */
    private function getTrackingData(string $errorHash): array
    {
        try {
            $directory = $this->getVarDirectory();
            $filePath = $this->getTrackingFilePath($errorHash);

            if (!$directory->isExist($filePath)) {
                return [];
            }

            $content = $directory->readFile($filePath);
            return $this->json->unserialize($content);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Save tracking data for error hash
     *
     * @param string $errorHash
     * @param array{
     *     error_hash: string,
     *     error_type: string,
     *     error_message: string,
     *     error_file: string,
     *     error_line: int,
     *     severity: string,
     *     store_id: int|null,
     *     url: string|null,
     *     occurrence_count: int,
     *     first_seen_at: int,
     *     last_seen_at: int,
     *     notification_count: int,
     *     last_notified_at: int|null
     * } $data
     * @return void
     */
    private function saveTrackingData(string $errorHash, array $data): void
    {
        try {
            $directory = $this->getVarDirectory();
            $filePath = $this->getTrackingFilePath($errorHash);

            // Create directory if it doesn't exist
            $trackingDir = self::ERROR_TRACKING_DIR;
            if (!$directory->isExist($trackingDir)) {
                $directory->create($trackingDir);
            }

            $content = $this->json->serialize($data);
            $directory->writeFile($filePath, $content);
        } catch (\Exception $e) {
            // Silently fail
        }
    }

    /**
     * Get tracking file path for error hash
     *
     * @param string $errorHash
     * @return string
     */
    private function getTrackingFilePath(string $errorHash): string
    {
        // Use first 2 characters for subdirectory (like Magento report system)
        $subDir = substr($errorHash, 0, 2);
        return self::ERROR_TRACKING_DIR . '/' . $subDir . '/' . self::TRACKING_FILE_PREFIX . $errorHash;
    }

    /**
     * Get var directory for writing
     *
     * @return WriteInterface
     */
    private function getVarDirectory(): WriteInterface
    {
        if ($this->varDirectory === null) {
            $this->varDirectory = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
        }

        return $this->varDirectory;
    }
}
