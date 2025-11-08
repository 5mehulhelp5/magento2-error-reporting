<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */
declare(strict_types=1);

namespace Hryvinskyi\ErrorReporting\Plugin\Framework\App;

use Hryvinskyi\ErrorReporting\Api\ConfigInterface;
use Hryvinskyi\ErrorReporting\Api\ErrorDataCollectorInterface;
use Hryvinskyi\ErrorReporting\Api\ErrorFilterInterface;
use Hryvinskyi\ErrorReporting\Api\Notification\NotificationDispatcherInterface;
use Hryvinskyi\ErrorReporting\Api\ThrottleServiceInterface;
use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\ExceptionHandler;
use Magento\Framework\App\Request\Http as RequestHttp;
use Magento\Framework\App\Response\Http as ResponseHttp;
use Psr\Log\LoggerInterface;

/**
 * Plugin for Magento\Framework\App\ExceptionHandler
 *
 * Intercepts exception handling to send notifications via unified notification dispatcher
 * Supports email (Magento Mail + PHP Sendmail fallback), Slack, Teams, and custom handlers
 */
class ExceptionHandlerPlugin
{
    /**
     * @param ConfigInterface $config
     * @param ErrorDataCollectorInterface $errorDataCollector
     * @param ErrorFilterInterface $errorFilter
     * @param ThrottleServiceInterface $throttleService
     * @param NotificationDispatcherInterface $notificationDispatcher
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly ConfigInterface $config,
        private readonly ErrorDataCollectorInterface $errorDataCollector,
        private readonly ErrorFilterInterface $errorFilter,
        private readonly ThrottleServiceInterface $throttleService,
        private readonly NotificationDispatcherInterface $notificationDispatcher,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Capture and process exception before handling
     *
     * @param ExceptionHandler $subject
     * @param Bootstrap $bootstrap
     * @param \Exception $exception
     * @param ResponseHttp $response
     * @param RequestHttp $request
     * @return array{
     *     0: Bootstrap,
     *     1: \Exception,
     *     2: ResponseHttp,
     *     3: RequestHttp
     * }
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeHandle(
        ExceptionHandler $subject,
        Bootstrap $bootstrap,
        \Exception $exception,
        ResponseHttp $response,
        RequestHttp $request
    ): array {
        try {
            // Check if module is enabled
            if (!$this->config->isEnabled()) {
                return [$bootstrap, $exception, $response, $request];
            }

            // Collect error data
            $errorData = $this->errorDataCollector->collect($exception, $request, $bootstrap);
            $errorHash = $errorData['error']['hash'] ?? '';
            $severity = $errorData['error']['severity'] ?? 'error';

            // Record error occurrence
            $this->throttleService->recordError($errorHash, $errorData);

            // Check if error should be reported (filtering)
            if (!$this->errorFilter->shouldReport($exception, $request, $severity)) {
                $this->logger->debug('Error filtered out, not reporting', [
                    'error_hash' => $errorHash,
                    'severity' => $severity,
                    'reason' => 'blacklist / whitelist or severity level'
                ]);
                return [$bootstrap, $exception, $response, $request];
            }

            // Check if notification should be sent (throttling)
            if (!$this->throttleService->shouldNotify($errorHash)) {
                $this->logger->debug('Error throttled, not sending notification', [
                    'error_hash' => $errorHash,
                    'severity' => $severity
                ]);
                return [$bootstrap, $exception, $response, $request];
            }

            // Dispatch to all notification handlers (Email, Slack, Teams, etc.)
            $notificationResults = $this->notificationDispatcher->dispatch($errorData);

            // Log results and mark as notified if any handler succeeded
            if (!empty(array_filter($notificationResults))) {
                $this->throttleService->markAsNotified($errorHash);

                $this->logger->info('Error notifications dispatched', [
                    'error_hash' => $errorHash,
                    'severity' => $severity,
                    'error_type' => $errorData['error']['type'] ?? 'Unknown',
                    'handlers' => $notificationResults
                ]);
            } else {
                $this->logger->warning('All notification handlers failed', [
                    'error_hash' => $errorHash,
                    'handlers' => $notificationResults
                ]);
            }
        } catch (\Exception $e) {
            // Don't let error reporting break the application
            $this->logger->error('Failed to process error notification', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }

        // Return original arguments unchanged
        return [$bootstrap, $exception, $response, $request];
    }
}
