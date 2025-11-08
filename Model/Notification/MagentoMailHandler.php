<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */
declare(strict_types=1);

namespace Hryvinskyi\ErrorReporting\Model\Notification;

use Hryvinskyi\ErrorReporting\Api\ConfigInterface;
use Hryvinskyi\ErrorReporting\Api\Filter\SeverityFilterInterface;
use Hryvinskyi\ErrorReporting\Api\Notification\FallbackNotificationHandlerInterface;
use Hryvinskyi\ErrorReporting\Api\Notification\NotificationHandlerInterface;
use Magento\Backend\App\Area\FrontNameResolver;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Magento Mail handler using TransportBuilder (SMTP/configured mail transport)
 *
 * Primary email notification handler using Magento's email system.
 * Supports fallback to another handler (e.g., PhpSendmailHandler) configured via di.xml
 */
class MagentoMailHandler implements FallbackNotificationHandlerInterface
{
    /**
     * @var NotificationHandlerInterface|null
     */
    private ?NotificationHandlerInterface $fallbackHandler = null;

    /**
     * @param ConfigInterface $config Configuration service for email settings
     * @param TransportBuilder $transportBuilder Magento email transport builder
     * @param StateInterface $inlineTranslation Inline translation state manager
     * @param StoreManagerInterface $storeManager Store manager for retrieving store information
     * @param LoggerInterface $logger Logger for error tracking
     * @param SeverityFilterInterface $severityFilter Centralized severity checking service
     * @param NotificationHandlerInterface|null $fallbackHandler Optional fallback handler
     */
    public function __construct(
        private readonly ConfigInterface $config,
        private readonly TransportBuilder $transportBuilder,
        private readonly StateInterface $inlineTranslation,
        private readonly StoreManagerInterface $storeManager,
        private readonly LoggerInterface $logger,
        private readonly SeverityFilterInterface $severityFilter,
        ?NotificationHandlerInterface $fallbackHandler = null
    ) {
        $this->fallbackHandler = $fallbackHandler;
    }

    /**
     * {@inheritDoc}
     */
    public function send(array $errorData): bool
    {
        if (!$this->config->isEnabled() || !$this->config->isEmailEnabled()) {
            return false;
        }

        $result = true;

        // Send to developers
        $developerEmails = $this->parseEmails($this->config->getDeveloperEmails());
        if (!empty($developerEmails)) {
            $result = $this->sendDeveloperEmail($errorData, $developerEmails) && $result;
        }

        // Send to clients if enabled
        if ($this->config->isClientNotificationEnabled()) {
            $clientEmails = $this->parseEmails($this->config->getClientEmails());
            if (!empty($clientEmails)) {
                $result = $this->sendClientEmail($errorData, $clientEmails) && $result;
            }
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function setFallbackHandler(?NotificationHandlerInterface $fallbackHandler): void
    {
        $this->fallbackHandler = $fallbackHandler;
    }

    /**
     * {@inheritDoc}
     */
    public function getFallbackHandler(): ?NotificationHandlerInterface
    {
        return $this->fallbackHandler;
    }

    /**
     * {@inheritDoc}
     */
    public function isEnabled(): bool
    {
        // Enabled if module and email notifications are enabled, and emails are configured
        return $this->config->isEnabled() &&
               $this->config->isEmailEnabled() &&
               !empty($this->config->getDeveloperEmails());
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return 'Magento Mail';
    }

    /**
     * {@inheritDoc}
     */
    public function shouldHandle(array $errorData): bool
    {
        // Check severity level using centralized severity filter
        $errorSeverity = $errorData['error']['severity'] ?? 'error';
        $minSeverity = $this->config->getEmailMinimumSeverity();

        return $this->severityFilter->meetsMinimumSeverity($errorSeverity, $minSeverity);
    }

    /**
     * Send detailed developer email
     *
     * @param array<string, mixed> $errorData
     * @param array<int, string> $emails
     * @return bool
     */
    private function sendDeveloperEmail(array $errorData, array $emails): bool
    {
        try {
            $this->inlineTranslation->suspend();
            $errorData['store'] = $this->storeManager->getStore(Store::DEFAULT_STORE_ID);

            $transport = $this->transportBuilder
                ->setTemplateIdentifier('hryvinskyi_error_reporting_developer')
                ->setTemplateOptions([
                    'area' => FrontNameResolver::AREA_CODE,
                    'store' => Store::DEFAULT_STORE_ID
                ])
                ->setTemplateVars($errorData)
                ->setFromByScope(
                    $this->config->getEmailSender(),
                    Store::DEFAULT_STORE_ID
                )
                ->addTo($emails)
                ->getTransport();

            $transport->sendMessage();
            $this->inlineTranslation->resume();

            $this->logger->info('Developer email sent via Magento Mail', [
                'error_hash' => $errorData['error']['hash'] ?? 'unknown',
                'recipients' => count($emails)
            ]);

            return true;
        } catch (\Throwable $e) {
            try {
                $this->inlineTranslation->resume();
            } catch (\Throwable $ignored) {
            }

            $this->logger->error('Failed to send developer email via Magento Mail', [
                'error' => $e->getMessage(),
                'error_class' => get_class($e)
            ]);

            return false;
        }
    }

    /**
     * Send simplified client email
     *
     * @param array<string, mixed> $errorData
     * @param array<int, string> $emails
     * @return bool
     */
    private function sendClientEmail(array $errorData, array $emails): bool
    {
        try {
            $this->inlineTranslation->suspend();

            // Remove sensitive data for client email
            $clientData = [
                'error' => [
                    'message' => 'An issue was detected',
                    'severity' => $errorData['error']['severity'] ?? 'error',
                ],
                'request' => [
                    'url' => $errorData['request']['url'] ?? '',
                    'method' => $errorData['request']['method'] ?? '',
                ],
                'store' => $this->storeManager->getStore(Store::DEFAULT_STORE_ID),
                'timestamp_formatted' => $errorData['timestamp_formatted'] ?? '',
                'frontend_store' => $errorData['frontend_store'] ?? [],
            ];

            $transport = $this->transportBuilder
                ->setTemplateIdentifier('hryvinskyi_error_reporting_client')
                ->setTemplateOptions([
                    'area' => FrontNameResolver::AREA_CODE,
                    'store' => Store::DEFAULT_STORE_ID
                ])
                ->setTemplateVars($clientData)
                ->setFromByScope(
                    $this->config->getEmailSender(),
                    Store::DEFAULT_STORE_ID
                )
                ->addTo($emails)
                ->getTransport();

            $transport->sendMessage();
            $this->inlineTranslation->resume();

            $this->logger->info('Client email sent via Magento Mail', [
                'error_hash' => $errorData['error']['hash'] ?? 'unknown',
                'recipients' => count($emails)
            ]);

            return true;
        } catch (\Throwable $e) {
            try {
                $this->inlineTranslation->resume();
            } catch (\Throwable $ignored) {
            }

            $this->logger->error('Failed to send client email via Magento Mail', [
                'error' => $e->getMessage(),
                'error_class' => get_class($e)
            ]);

            return false;
        }
    }

    /**
     * Parse comma-separated email addresses
     *
     * @param string $emailString
     * @return array<int, string>
     */
    private function parseEmails(string $emailString): array
    {
        $emails = array_map('trim', explode(',', $emailString));
        return array_filter($emails, function ($email) {
            return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
        });
    }
}
