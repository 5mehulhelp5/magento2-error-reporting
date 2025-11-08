<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */
declare(strict_types=1);

namespace Hryvinskyi\ErrorReporting\Model;

use Hryvinskyi\Base\Helper\VarDumper;
use Hryvinskyi\ErrorReporting\Api\Collector\DataSanitizerInterface;
use Hryvinskyi\ErrorReporting\Api\Collector\ErrorHashGeneratorInterface;
use Hryvinskyi\ErrorReporting\Api\Collector\RequestDataCollectorInterface;
use Hryvinskyi\ErrorReporting\Api\Collector\SeverityResolverInterface;
use Hryvinskyi\ErrorReporting\Api\ConfigInterface;
use Hryvinskyi\ErrorReporting\Api\ErrorDataCollectorInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\Request\Http as RequestHttp;
use Magento\Store\Model\StoreManager;

/**
 * Service for collecting comprehensive error data
 *
 * Coordinates multiple collector services following Single Responsibility Principle.
 * Each collector service handles one specific aspect of data collection.
 */
class ErrorDataCollector implements ErrorDataCollectorInterface
{
    /**
     * @param ConfigInterface $config
     * @param StoreManager $storeManager
     * @param ErrorHashGeneratorInterface $errorHashGenerator
     * @param SeverityResolverInterface $severityResolver
     * @param RequestDataCollectorInterface $requestDataCollector
     * @param DataSanitizerInterface $dataSanitizer
     * @param CustomerSession $customerSession
     */
    public function __construct(
        private readonly ConfigInterface $config,
        private readonly StoreManager $storeManager,
        private readonly ErrorHashGeneratorInterface $errorHashGenerator,
        private readonly SeverityResolverInterface $severityResolver,
        private readonly RequestDataCollectorInterface $requestDataCollector,
        private readonly DataSanitizerInterface $dataSanitizer,
        private readonly CustomerSession $customerSession,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function collect(
        \Exception $exception,
        RequestHttp $request,
        Bootstrap $bootstrap
    ): array {
        // Collect request and client data using dedicated service
        $requestData = $this->requestDataCollector->collect($request);

        $data = array_merge([
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

            // Timestamp
            'timestamp' => date('Y-m-d H:i:s'),
            'timestamp_formatted' => date('F j, Y g:i:s A T'),

            // Store context
            'frontend_store' => $this->collectStoreData($request, $requestData['area'] ?? 'frontend'),

            // User context
            'user' => $this->collectCustomerData($requestData['area'] ?? 'frontend'),
            'post_data' => null
        ], $requestData); // Merge request data from collector

        // Add detailed information if enabled
        if ($this->config->includeDetailedInfo()) {
            $data['trace'] = $exception->getTraceAsString();

            // Previous exceptions chain
            $previous = $exception->getPrevious();
            if ($previous) {
                $data['previous_exceptions'] = $this->collectPreviousExceptions($previous);
            }

            // Server environment
            $data['environment'] = $this->collectEnvironmentData();
        }

        // Add POST data if enabled (sanitized)
        if ($this->config->includePostData() && isset($_POST) && !empty($_POST)) {
            $sanitized = $this->dataSanitizer->sanitize($_POST);
            $data['post_data'] = VarDumper::dumpAsString($sanitized, 10, true);
        }

        return $data;
    }

    /**
     * {@inheritDoc}
     */
    public function getErrorHash(\Exception $exception): string
    {
        return $this->errorHashGenerator->generate($exception);
    }

    /**
     * {@inheritDoc}
     */
    public function getSeverityLevel(\Exception $exception): string
    {
        return $this->severityResolver->resolve($exception);
    }

    /**
     * Collect previous exceptions in chain
     *
     * @param \Throwable $exception
     * @return array<int, array{
     *     index: int,
     *     type: string,
     *     message: string,
     *     file: string,
     *     line: int
     * }>
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
     * Collect store data with fallback
     *
     * @param RequestHttp $request
     * @param string $area
     * @return array{
     *     id?: int,
     *     name: string,
     *     code: string,
     *     base_url: string,
     *     mage_run_code: string|null,
     *     mage_run_type: string|null
     * }
     */
    private function collectStoreData(RequestHttp $request, string $area): array
    {
        $storeData = [
            'name' => $request->getHttpHost(),
            'code' => 'default',
            'base_url' => sprintf('%s://%s', $request->getScheme(), $request->getHttpHost()),
            'mage_run_code' => $request->getParam(StoreManager::PARAM_RUN_CODE),
            'mage_run_type' => $request->getParam(StoreManager::PARAM_RUN_TYPE),
        ];

        // Try to get actual store information based on current request context
        try {
            // Get the current store from the request context
            $store = $this->storeManager->getStore();

            // For admin area, explicitly use admin store
            if ($area === 'adminhtml') {
                $store = $this->storeManager->getStore(\Magento\Store\Model\Store::ADMIN_CODE);
            }

            $storeData['id'] = $store->getId();
            $storeData['name'] = $store->getName();
            $storeData['code'] = $store->getCode();
            $storeData['base_url'] = $store->getBaseUrl();
        } catch (\Throwable $e) {
            // Use fallback data if store manager fails
        }

        return $storeData;
    }

    /**
     * Collect customer data with fallback
     *
     * @param string $area
     * @return array{
     *     type: string,
     *     id: int|null,
     *     name: string|null,
     *     email: string|null
     * }
     */
    private function collectCustomerData(string $area): array
    {
        $customerData = [
            'type' => 'guest',
            'id' => null,
            'name' => null,
            'email' => null,
        ];

        // Only try to get customer data for frontend area
        if ($area !== 'frontend') {
            return $customerData;
        }

        // Try to get actual customer information from session
        try {
            if ($this->customerSession->isLoggedIn()) {
                $customer = $this->customerSession->getCustomer();

                $customerData['type'] = 'customer';
                $customerData['id'] = $customer->getId();
                $customerData['name'] = $customer->getName();
                $customerData['email'] = $customer->getEmail();
            }
        } catch (\Throwable $e) {
            // Use fallback data if customer session fails
        }

        return $customerData;
    }

    /**
     * Collect server environment data
     *
     * @return array{
     *     php_version: string,
     *     memory_usage: string,
     *     memory_peak: string,
     *     memory_limit: string
     * }
     */
    private function collectEnvironmentData(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'memory_usage' => $this->formatBytes(memory_get_usage(true)),
            'memory_peak' => $this->formatBytes(memory_get_peak_usage(true)),
            'memory_limit' => ini_get('memory_limit'),
        ];
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

