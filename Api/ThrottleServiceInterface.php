<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */
declare(strict_types=1);

namespace Hryvinskyi\ErrorReporting\Api;

/**
 * Interface for throttling error notifications to prevent spam
 */
interface ThrottleServiceInterface
{
    /**
     * Check if error notification should be sent (throttling)
     *
     * @param string $errorHash
     * @param int|null $storeId
     * @return bool
     */
    public function shouldNotify(string $errorHash, ?int $storeId = null): bool;

    /**
     * Record error occurrence
     *
     * @param string $errorHash
     * @param array<string, mixed> $errorData
     * @param int|null $storeId
     * @return void
     */
    public function recordError(string $errorHash, array $errorData, ?int $storeId = null): void;

    /**
     * Mark error as notified
     *
     * @param string $errorHash
     * @return void
     */
    public function markAsNotified(string $errorHash): void;
}
