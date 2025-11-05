<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */
declare(strict_types=1);

namespace Hryvinskyi\ErrorReporting\Api;

use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\Request\Http as RequestHttp;

/**
 * Interface for collecting extended error information
 */
interface ErrorDataCollectorInterface
{
    /**
     * Collect comprehensive error data
     *
     * @param \Exception $exception
     * @param RequestHttp $request
     * @param Bootstrap $bootstrap
     * @return array<string, mixed>
     */
    public function collect(
        \Exception $exception,
        RequestHttp $request,
        Bootstrap $bootstrap
    ): array;

    /**
     * Get error hash for grouping same errors
     *
     * @param \Exception $exception
     * @return string
     */
    public function getErrorHash(\Exception $exception): string;

    /**
     * Determine error severity level
     *
     * @param \Exception $exception
     * @return string (critical|error|warning)
     */
    public function getSeverityLevel(\Exception $exception): string;
}
