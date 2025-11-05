<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */
declare(strict_types=1);

namespace Hryvinskyi\ErrorReporting\Api;

use Magento\Framework\App\Request\Http as RequestHttp;

/**
 * Interface for filtering errors based on blacklist and severity
 */
interface ErrorFilterInterface
{
    /**
     * Check if error should be reported
     *
     * @param \Exception $exception
     * @param RequestHttp $request
     * @param string $severity
     * @return bool
     */
    public function shouldReport(\Exception $exception, RequestHttp $request, string $severity): bool;

    /**
     * Check if error matches blacklist patterns
     *
     * @param \Throwable $exception
     * @return bool
     */
    public function isBlacklistedException(\Throwable $exception): bool;

    /**
     * Check if controller is blacklisted based on configuration
     *
     * @param RequestHttp $request
     * @return bool
     */
    public function isBlacklistedController(RequestHttp $request): bool;
}
