<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */
declare(strict_types=1);

namespace Hryvinskyi\ErrorReporting\Model;

use Hryvinskyi\Base\Helper\VarDumper;
use Hryvinskyi\ErrorReporting\Api\ConfigInterface;
use Hryvinskyi\ErrorReporting\Api\ErrorDataCollectorInterface;
use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\Request\Http as RequestHttp;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManager;

/**
 * Service for collecting comprehensive error data
 */
class ErrorDataCollector implements ErrorDataCollectorInterface
{
    public function __construct(
        private readonly ConfigInterface $config,
        private readonly StoreManager $storeManager,
        private readonly array $sensitiveFields = [],
    ) {
    }

    /**
     * @inheritDoc
     */
    public function collect(
        \Exception $exception,
        RequestHttp $request,
        Bootstrap $bootstrap
    ): array {
        $data = [
            // Basic error information
            'error' => [
                'message' => $exception->getMessage(),
                'type' => get_class($exception),
                'code' => $exception->getCode(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'hash' => $this->getErrorHash($exception),
                'severity' => $this->getSeverityLevel($exception),
            ],

            // Request information
            'request' => [
                'url' => $request->getScheme() . '://' . $request->getHttpHost() . $request->getRequestUri(),
                'method' => $request->getMethod(),
                'is_ajax' => $request->isAjax(),
                'is_secure' => $request->isSecure(),
            ],

            // Client information
            'client' => [
                'ip' => $request->getClientIp(),
                'user_agent' => $request->getHeader('User-Agent'),
                'referer' => $request->getHeader('Referer'),
            ],

            // Timestamp
            'timestamp' => date('Y-m-d H:i:s'),
            'timestamp_formatted' => date('F j, Y g:i:s A T'),

            // Store context (simplified - no database dependency)
            'frontend_store' => [
                'name' => $request->getHttpHost(),
                'code' => 'default',
                'base_url' => $request->getScheme() . '://' . $request->getHttpHost(),
                // Get store code from request (MAGE_STORE_CODE) if available
                'mage_run_code' => $request->getParam(StoreManager::PARAM_RUN_CODE, 'default'),
                'mage_run_type' => $request->getParam(StoreManager::PARAM_RUN_TYPE, 'default'),
            ],

            // Area
            'area' => $this->detectArea($request),

            // User context (simplified - no session dependency)
            'user' => [
                'type' => 'guest', // Default, can be enhanced if sessions available
                'name' => null,
                'email' => null,
            ],
            'post_data' => null
        ];

        // Add detailed information if enabled
        if ($this->config->includeDetailedInfo()) {
            $data['trace'] = $exception->getTraceAsString();

            // Previous exceptions chain
            $previous = $exception->getPrevious();
            if ($previous) {
                $data['previous_exceptions'] = $this->collectPreviousExceptions($previous);
            }

            // Server environment
            $data['environment'] = [
                'php_version' => PHP_VERSION,
                'memory_usage' => $this->formatBytes(memory_get_usage(true)),
                'memory_peak' => $this->formatBytes(memory_get_peak_usage(true)),
                'memory_limit' => ini_get('memory_limit'),
            ];
        }

        // Add POST data if enabled (sanitized)
        if ($this->config->includePostData() && isset($_POST)) {
            $data['post_data'] = VarDumper::dumpAsString($this->sanitizePostData($_POST), 10, true);
        }

        try {
            $store = $this->storeManager->getStore();
            $data['frontend_store']['name'] = $store->getName();
            $data['frontend_store']['code'] = $store->getCode();
            $data['frontend_store']['base_url'] = $store->getBaseUrl();
        } catch (\Throwable $e) {
        }

        return $data;
    }

    /**
     * @inheritDoc
     */
    public function getErrorHash(\Exception $exception): string
    {
        // Create hash from exception class, message, file, and line
        // Same errors will have the same hash for grouping
        $hashData = sprintf(
            '%s:%s:%s:%d',
            get_class($exception),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        );

        return hash('sha256', $hashData);
    }

    /**
     * @inheritDoc
     */
    public function getSeverityLevel(\Exception $exception): string
    {
        // Determine severity based on exception type
        $exceptionClass = get_class($exception);

        // Critical errors
        $criticalExceptions = [
            'Error',
            'ParseError',
            'TypeError',
            'PDOException',
        ];

        foreach ($criticalExceptions as $critical) {
            if (strpos($exceptionClass, $critical) !== false) {
                return 'critical';
            }
        }

        // Warning level exceptions
        $warningExceptions = [
            'NoSuchEntity',
            'NotFoundException',
        ];

        foreach ($warningExceptions as $warning) {
            if (strpos($exceptionClass, $warning) !== false) {
                return 'warning';
            }
        }

        // Default to error
        return 'error';
    }

    /**
     * Detect application area from request
     *
     * @param RequestHttp $request
     * @return string
     */
    private function detectArea(RequestHttp $request): string
    {
        $path = $request->getRequestUri();

        if (strpos($path, '/admin/') !== false) {
            return 'adminhtml';
        }

        if (strpos($path, '/rest/') !== false) {
            return 'webapi_rest';
        }

        if (strpos($path, '/soap/') !== false) {
            return 'webapi_soap';
        }

        return 'frontend';
    }

    /**
     * Collect previous exceptions in chain
     *
     * @param \Throwable $exception
     * @return array<int, array<string, mixed>>
     */
    private function collectPreviousExceptions(\Throwable $exception): array
    {
        $exceptions = [];
        $index = 0;

        do {
            $exceptions[] = [
                'index' => $index++,
                'type' => get_class($exception),
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ];
        } while ($exception = $exception->getPrevious());

        return $exceptions;
    }

    /**
     * Sanitize POST data to remove sensitive information
     *
     * @param array<string, mixed> $postData
     * @return array<string, mixed>
     */
    private function sanitizePostData(array $postData): array
    {
        $defaultSensitiveFields = [
            'password',
            'password_confirmation',
            'current_password',
            'new_password',
            'cc_number',
            'cc_cid',
            'cvv',
            'card_number',
            'api_key',
            'secret',
            'token',
        ];

        $sensitiveFields = array_merge($defaultSensitiveFields, $this->sensitiveFields);

        foreach ($postData as $key => $value) {
            $lowerKey = strtolower((string)$key);
            foreach ($sensitiveFields as $sensitiveField) {
                if (str_contains((string)$lowerKey, (string)$sensitiveField)) {
                    $postData[$key] = '***REDACTED***';
                    break;
                }
            }

            if (is_array($value)) {
                $postData[$key] = $this->sanitizePostData($value);
            }
        }

        return $postData;
    }

    /**
     * Format bytes to human-readable format
     *
     * @param int $bytes
     * @return string
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $power = $bytes > 0 ? floor(log($bytes, 1024)) : 0;

        return sprintf(
            '%.2f %s',
            $bytes / (1024 ** $power),
            $units[$power] ?? 'GB'
        );
    }
}
