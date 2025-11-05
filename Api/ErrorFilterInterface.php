<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */
declare(strict_types=1);

namespace Hryvinskyi\ErrorReporting\Api;

/**
 * Interface for filtering errors based on blacklist and severity
 */
interface ErrorFilterInterface
{
    /**
     * Check if error should be reported
     *
     * @param \Exception $exception
     * @param string $severity
     * @param int|null $storeId
     * @return bool
     */
    public function shouldReport(\Exception $exception, string $severity, ?int $storeId = null): bool;

    /**
     * Check if error matches blacklist patterns
     *
     * @param \Exception $exception
     * @param int|null $storeId
     * @return bool
     */
    public function isBlacklisted(\Exception $exception, ?int $storeId = null): bool;
}
