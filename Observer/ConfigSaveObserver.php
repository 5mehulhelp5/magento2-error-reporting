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
 *
 * Captures configuration directly from POST data to avoid database dependency
 */
class ConfigSaveObserver implements ObserverInterface
{
    /**
     * @param ConfigStorageInterface $configStorage
     * @param RequestInterface $request
     * @param ManagerInterface $messageManager
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly ConfigStorageInterface $configStorage,
        private readonly RequestInterface $request,
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
                // Get configuration from POST data
                $groups = $this->request->getParam('groups', []);

                if (isset($groups['hryvinskyi_error_reporting'])) {
                    $groups = $groups['hryvinskyi_error_reporting'];
                }

                // Extract configuration values directly from POST data
                $config = [
                    'enabled' => $groups['general']['fields']['enabled']['value'] ?? '0',
                    'developer_emails' => $groups['general']['fields']['developer_emails']['value'] ?? '',
                    'client_notification_enabled' => $groups['general']['fields']['client_notification_enabled']['value'] ?? '0',
                    'client_emails' => $groups['general']['fields']['client_emails']['value'] ?? '',
                    'email_sender' => $groups['general']['fields']['email_sender']['value'] ?? 'general',
                    'error_blacklist' => $groups['filtering']['fields']['error_blacklist']['value'] ?? '',
                    'throttle_period' => $groups['throttling']['fields']['period_minutes']['value'] ?? '60',
                    'max_errors_per_period' => $groups['throttling']['fields']['max_errors_per_period']['value'] ?? '5',
                    'include_detailed_info' => $groups['details']['fields']['include_detailed_info']['value'] ?? '1',
                    'include_post_data' => $groups['details']['fields']['include_post_data']['value'] ?? '0',
                    'minimum_severity' => $groups['filtering']['fields']['minimum_severity']['value'] ?? 'error',
                ];

                // Save directly to filesystem (bypassing database)
                $result = $this->configStorage->saveConfig($config);

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

                // Fallback to exportConfig if direct save fails
                try {
                    $this->configStorage->exportConfig();
                } catch (\Exception $fallbackException) {
                    $this->messageManager->addErrorMessage(
                        __('An error occurred while exporting configuration: %1', $e->getMessage())
                    );
                }
            }
        }
    }
}
