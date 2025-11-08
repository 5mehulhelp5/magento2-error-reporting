<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */
declare(strict_types=1);

namespace Hryvinskyi\ErrorReporting\Api\Notification;

/**
 * Interface for dispatching notifications to multiple handlers
 *
 * Coordinates sending error notifications to all registered handlers
 * (Slack, Teams, custom webhooks, etc.)
 */
interface NotificationDispatcherInterface
{
    /**
     * Dispatch error notification to all enabled handlers
     *
     * @param array<string, mixed> $errorData Complete error data from ErrorDataCollector
     * @return array<string, bool> Map of handler names to success status
     */
    public function dispatch(array $errorData): array;

    /**
     * Get all registered handlers
     *
     * @return NotificationHandlerInterface[]
     */
    public function getHandlers(): array;
}
