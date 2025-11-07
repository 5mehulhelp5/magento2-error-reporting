<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */
declare(strict_types=1);

namespace Hryvinskyi\ErrorReporting\Model\Collector;

use Hryvinskyi\ErrorReporting\Api\Collector\RequestDataCollectorInterface;
use Magento\Framework\App\Request\Http as RequestHttp;

/**
 * Service for collecting HTTP request data
 */
class RequestDataCollector implements RequestDataCollectorInterface
{
    /**
     * {@inheritDoc}
     */
    public function collect(RequestHttp $request): array
    {
        return [
            'request' => [
                'url' => $this->getFullUrl($request),
                'method' => $request->getMethod(),
                'is_ajax' => $request->isAjax(),
                'is_secure' => $request->isSecure(),
            ],
            'client' => [
                'ip' => $request->getClientIp(),
                'user_agent' => $request->getHeader('User-Agent'),
                'referer' => $request->getHeader('Referer'),
            ],
            'area' => $this->detectArea($request),
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function detectArea(RequestHttp $request): string
    {
        $path = $request->getRequestUri();

        if (str_contains($path, '/admin/') || str_contains($path, '/backend/')) {
            return 'adminhtml';
        }

        if (str_contains($path, '/rest/')) {
            return 'webapi_rest';
        }

        if (str_contains($path, '/soap/')) {
            return 'webapi_soap';
        }

        if (str_contains($path, '/graphql')) {
            return 'graphql';
        }

        return 'frontend';
    }

    /**
     * Get full URL from request
     *
     * @param RequestHttp $request
     * @return string
     */
    private function getFullUrl(RequestHttp $request): string
    {
        return sprintf(
            '%s://%s%s',
            $request->getScheme(),
            $request->getHttpHost(),
            $request->getRequestUri()
        );
    }
}