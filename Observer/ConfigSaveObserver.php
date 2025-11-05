<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */
declare(strict_types=1);

namespace Hryvinskyi\ErrorReporting\Observer;

use Hryvinskyi\ErrorReporting\Api\ConfigStorageInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Message\ManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Observer to auto-export error reporting configuration to filesystem on save
 */
class ConfigSaveObserver implements ObserverInterface
{
    public function __construct(
        private readonly ConfigStorageInterface $configStorage,
        private readonly ManagerInterface $messageManager,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Execute observer
     *
     * Captures configuration directly from request and saves to filesystem
     * This avoids database dependency during the save process
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        /** @var string|null $changedSection */
        $changedSection = $observer->getData('changed_section');

        // Only export if error reporting configuration was saved
        if ($changedSection === null || $changedSection === 'hryvinskyi_error_reporting') {
            try {
                // Save directly to filesystem
                $result = $this->configStorage->saveConfig($this->configStorage->exportConfig());

                if ($result) {
                    $this->messageManager->addSuccessMessage(
                        __('Error reporting configuration has been exported for failover support.')
                    );

                    $this->logger->info('Error reporting configuration saved directly to filesystem from admin save');
                } else {
                    $this->messageManager->addWarningMessage(
                        __('Failed to export error reporting configuration. Failover may not work.')
                    );
                }
            } catch (\Exception $e) {
                $this->logger->error('Failed to save error reporting configuration directly', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }
    }
}
