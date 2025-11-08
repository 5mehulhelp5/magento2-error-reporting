<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */
declare(strict_types=1);

namespace Hryvinskyi\ErrorReporting\Model\Notification;

use Hryvinskyi\ErrorReporting\Api\Notification\FallbackNotificationHandlerInterface;
use Hryvinskyi\ErrorReporting\Api\Notification\NotificationDispatcherInterface;
use Hryvinskyi\ErrorReporting\Api\Notification\NotificationHandlerInterface;
use Psr\Log\LoggerInterface;

/**
 * Dispatches error notifications to multiple handlers
 *
 * Follows Single Responsibility Principle - only coordinates handler execution.
 * Handlers are injected via di.xml configuration.
 */
class NotificationDispatcher implements NotificationDispatcherInterface
{
    /**
     * @var NotificationHandlerInterface[]
     */
    private array $handlers = [];

    /**
     * @param array<string, NotificationHandlerInterface> $handlers Injected via di.xml
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly LoggerInterface $logger,
        array $handlers = [],
    ) {
        foreach ($handlers as $handler) {
            if ($handler instanceof NotificationHandlerInterface) {
                $this->handlers[] = $handler;
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    public function dispatch(array $errorData): array
    {
        $results = [];

        foreach ($this->handlers as $handler) {
            $handlerName = $handler->getName();

            try {
                // Check if handler is enabled and should handle this error
                if (!$handler->isEnabled()) {
                    $this->logger->debug("Notification handler {$handlerName} is disabled, skipping");
                    continue;
                }

                if (!$handler->shouldHandle($errorData)) {
                    $this->logger->debug("Notification handler {$handlerName} filtered error, skipping", [
                        'error_hash' => $errorData['error']['hash'] ?? 'unknown'
                    ]);
                    continue;
                }

                // Send notification
                $success = $handler->send($errorData);

                // If failed and handler supports fallback, try fallback
                if (!$success && $handler instanceof FallbackNotificationHandlerInterface) {
                    $fallbackHandler = $handler->getFallbackHandler();
                    if ($fallbackHandler !== null) {
                        $this->logger->info(
                            "Handler {$handlerName} failed, attempting fallback: " . $fallbackHandler->getName()
                        );

                        try {
                            $success = $fallbackHandler->send($errorData);
                            if ($success) {
                                $this->logger->info(
                                    "Fallback handler {$fallbackHandler->getName()} succeeded for {$handlerName}"
                                );
                            }
                        } catch (\Throwable $fallbackException) {
                            $this->logger->error(
                                "Fallback handler {$fallbackHandler->getName()} failed",
                                [
                                    'error' => $fallbackException->getMessage(),
                                    'trace' => $fallbackException->getTraceAsString()
                                ]
                            );
                        }
                    }
                }

                $results[$handlerName] = $success;

                if ($success) {
                    $this->logger->info("Notification sent successfully via {$handlerName}", [
                        'error_hash' => $errorData['error']['hash'] ?? 'unknown',
                        'severity' => $errorData['error']['severity'] ?? 'unknown'
                    ]);
                } else {
                    $this->logger->warning("Failed to send notification via {$handlerName}", [
                        'error_hash' => $errorData['error']['hash'] ?? 'unknown'
                    ]);
                }
            } catch (\Throwable $e) {
                // Don't let notification failures break error reporting
                $this->logger->error("Exception in notification handler {$handlerName}", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                $results[$handlerName] = false;
            }
        }

        return $results;
    }

    /**
     * {@inheritDoc}
     */
    public function getHandlers(): array
    {
        return $this->handlers;
    }
}
