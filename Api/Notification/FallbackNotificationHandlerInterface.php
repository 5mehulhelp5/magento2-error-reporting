<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */
declare(strict_types=1);

namespace Hryvinskyi\ErrorReporting\Api\Notification;

/**
 * Interface for notification handlers that support fallback
 *
 * Allows configuring a fallback handler to use when the primary handler fails
 */
interface FallbackNotificationHandlerInterface extends NotificationHandlerInterface
{
    /**
     * Set fallback handler to use if this handler fails
     *
     * @param NotificationHandlerInterface|null $fallbackHandler
     * @return void
     */
    public function setFallbackHandler(?NotificationHandlerInterface $fallbackHandler): void;

    /**
     * Get fallback handler
     *
     * @return NotificationHandlerInterface|null
     */
    public function getFallbackHandler(): ?NotificationHandlerInterface;
}
