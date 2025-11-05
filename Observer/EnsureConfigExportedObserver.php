<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */
declare(strict_types=1);

namespace Hryvinskyi\ErrorReporting\Observer;

use Hryvinskyi\ErrorReporting\Api\ConfigStorageInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;

/**
 * Observer to ensure configuration is exported on every controller dispatch
 *
 * This is particularly useful after deployment when var/ directory may be cleared
 * or when configuration file is missing for any reason
 */
class EnsureConfigExportedObserver implements ObserverInterface
{
    private static bool $checked = false;

    /**
     * @param ConfigStorageInterface $configStorage
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly ConfigStorageInterface $configStorage,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Execute observer
     *
     * Checks if configuration file exists on every controller dispatch.
     * If missing, exports it automatically.
     * Only checks once per request to avoid performance impact.
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        // Only check once per request
        if (self::$checked) {
            return;
        }

        self::$checked = true;

        try {
            // Check if configuration file exists
            if (!$this->configStorage->hasConfig()) {
                $this->logger->warning(
                    'Error reporting configuration file missing, attempting to export...'
                );

                // Attempt to export configuration
                $result = $this->configStorage->exportConfig();

                if ($result) {
                    $this->logger->info(
                        'Error reporting configuration automatically exported after detection of missing file'
                    );
                } else {
                    $this->logger->error(
                        'Failed to automatically export error reporting configuration'
                    );
                }
            }
        } catch (\Throwable $e) {
            // Silently catch exceptions to avoid breaking the application
            // Log the error but don't throw
            $this->logger->error('Error in EnsureConfigExportedObserver', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
