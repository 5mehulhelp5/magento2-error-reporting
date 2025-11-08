<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */
declare(strict_types=1);

namespace Hryvinskyi\ErrorReporting\Api\Notification;

/**
 * Interface for notification handlers (Slack, Teams, PagerDuty, webhooks, etc.)
 *
 * Allows sending error notifications to external services via webhooks/APIs.
 * Implement this interface to create custom notification handlers.
 */
interface NotificationHandlerInterface
{
    /**
     * Send error notification to external service
     *
     * @param array<string, mixed> $errorData Complete error data from ErrorDataCollector
     * @return bool True if notification was sent successfully
     */
    public function send(array $errorData): bool;

    /**
     * Check if this handler is enabled in configuration
     *
     * @return bool
     */
    public function isEnabled(): bool;

    /**
     * Get handler name for logging/debugging
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Check if this handler should process the given error
     *
     * Allows filtering based on severity, error type, etc.
     *
     * @param array<string, mixed> $errorData
     * @return bool
     */
    public function shouldHandle(array $errorData): bool;
}
