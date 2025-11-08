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
     * @param array{
     *     error: array{
     *         message: string,
     *         type: string,
     *         code: int|string,
     *         file: string,
     *         line: int,
     *         hash: string,
     *         severity: string
     *     },
     *     timestamp: string,
     *     timestamp_formatted: string,
     *     frontend_store: array{
     *         id?: int,
     *         name: string,
     *         code: string,
     *         base_url: string,
     *         mage_run_code: string|null,
     *         mage_run_type: string|null
     *     },
     *     user: array{
     *         type: string,
     *         id: int|null,
     *         name: string|null,
     *         email: string|null
     *     },
     *     request: array{
     *         url: string,
     *         method: string,
     *         is_ajax: bool,
     *         is_secure: bool,
     *         controller_action?: string,
     *         ip?: string,
     *         user_agent?: string,
     *         area?: string
     *     },
     *     client: array{
     *         ip: string|false,
     *         user_agent: string|false,
     *         referer: string|false
     *     },
     *     area: string,
     *     post_data: string|null,
     *     trace?: string,
     *     previous_exceptions?: array<int, array{
     *         index: int,
     *         type: string,
     *         message: string,
     *         file: string,
     *         line: int
     *     }>,
     *     environment?: array{
     *         php_version: string,
     *         memory_usage: string,
     *         memory_peak: string,
     *         memory_limit: string
     *     }
     * } $errorData
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
