<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */
declare(strict_types=1);

namespace Hryvinskyi\ErrorReporting\Api\Collector;

use Magento\Framework\App\Request\Http as RequestHttp;

/**
 * Service for collecting HTTP request data
 */
interface RequestDataCollectorInterface
{
    /**
     * Collect HTTP request information
     *
     * @param RequestHttp $request
     * @return array{
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
     *     area: string
     * }
     */
    public function collect(RequestHttp $request): array;

    /**
     * Detect application area from request
     *
     * @param RequestHttp $request
     * @return string Area code (frontend, adminhtml, webapi_rest, etc.)
     */
    public function detectArea(RequestHttp $request): string;
}