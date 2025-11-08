<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */
declare(strict_types=1);

namespace Hryvinskyi\ErrorReporting\Model\Notification;

use Hryvinskyi\ErrorReporting\Api\ConfigInterface;
use Hryvinskyi\ErrorReporting\Api\Filter\SeverityFilterInterface;
use Hryvinskyi\ErrorReporting\Api\Notification\NotificationHandlerInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Microsoft Teams notification handler
 *
 * Sends error notifications to Microsoft Teams via incoming webhook using Adaptive Cards
 */
class TeamsHandler implements NotificationHandlerInterface
{
    /**
     * @param ConfigInterface $config Configuration service for Teams settings
     * @param ClientInterface $httpClient PSR-18 HTTP client for making requests
     * @param RequestFactoryInterface $requestFactory PSR-17 request factory for creating requests
     * @param StreamFactoryInterface $streamFactory PSR-17 stream factory for creating request body
     * @param Json $json JSON serializer for encoding payload
     * @param LoggerInterface $logger Logger for error tracking
     * @param SeverityFilterInterface $severityFilter Centralized severity checking service
     */
    public function __construct(
        private readonly ConfigInterface $config,
        private readonly ClientInterface $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly Json $json,
        private readonly LoggerInterface $logger,
        private readonly SeverityFilterInterface $severityFilter
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function send(array $errorData): bool
    {
        try {
            $webhookUrl = $this->getWebhookUrl();
            if (empty($webhookUrl)) {
                $this->logger->warning('Teams webhook URL is not configured');
                return false;
            }

            $payload = $this->buildTeamsPayload($errorData);
            $jsonPayload = $this->json->serialize($payload);

            // Create PSR-7 request with JSON payload
            $request = $this->requestFactory
                ->createRequest('POST', $webhookUrl)
                ->withHeader('Content-Type', 'application/json')
                ->withBody($this->streamFactory->createStream($jsonPayload));

            // Send request using PSR-18 HTTP client
            $response = $this->httpClient->sendRequest($request);

            $statusCode = $response->getStatusCode();
            $responseBody = (string) $response->getBody();

            if ($statusCode === 200) {
                return true;
            }

            $this->logger->error('Teams webhook returned non-200 status', [
                'status_code' => $statusCode,
                'response' => $responseBody,
                'webhook_url' => $webhookUrl
            ]);

            return false;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send Teams notification', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function isEnabled(): bool
    {
        return $this->config->isTeamsEnabled();
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return 'Microsoft Teams';
    }

    /**
     * {@inheritDoc}
     */
    public function shouldHandle(array $errorData): bool
    {
        // Check severity level using centralized severity filter
        $severity = $errorData['error']['severity'] ?? 'error';
        $minSeverity = $this->config->getTeamsMinimumSeverity();

        return $this->severityFilter->meetsMinimumSeverity($severity, $minSeverity);
    }

    /**
     * Get Teams webhook URL from configuration
     *
     * @return string
     */
    private function getWebhookUrl(): string
    {
        return $this->config->getTeamsWebhookUrl();
    }

    /**
     * Build Teams message payload using MessageCard format
     *
     * Uses Office 365 Connector Card format for compatibility
     *
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
     * @return array{
     *     '@type': string,
     *     '@context': string,
     *     summary: string,
     *     themeColor: string,
     *     title: string,
     *     sections: array<int, array{
     *         activityTitle?: string,
     *         activitySubtitle?: string,
     *         title?: string,
     *         facts: array<int, array{
     *             name: string,
     *             value: string
     *         }>,
     *         markdown?: bool
     *     }>,
     *     potentialAction?: array<int, array{
     *         '@type': string,
     *         name: string,
     *         targets: array<int, array{
     *             os: string,
     *             uri: string
     *         }>
     *     }>
     * }
     */
    private function buildTeamsPayload(array $errorData): array
    {
        $error = $errorData['error'] ?? [];
        $request = $errorData['request'] ?? [];
        $store = $errorData['frontend_store'] ?? [];
        $user = $errorData['user'] ?? [];

        // Determine theme color based on severity
        $themeColor = match ($error['severity'] ?? 'error') {
            'critical' => 'FF0000',   // Red
            'error' => 'FFA500',      // Orange
            'warning' => 'FFCC00',    // Yellow
            default => '808080'       // Gray
        };

        // Emoji based on severity
        $emoji = match ($error['severity'] ?? 'error') {
            'critical' => '🚨',
            'error' => '⚠️',
            'warning' => '🔶',
            default => '❓'
        };

        // Build MessageCard
        $card = [
            '@type' => 'MessageCard',
            '@context' => 'https://schema.org/extensions',
            'summary' => sprintf(
                '%s: %s',
                $error['type'] ?? 'Error',
                substr($error['message'] ?? 'Unknown error', 0, 100)
            ),
            'themeColor' => $themeColor,
            'title' => sprintf(
                '%s %s Error Detected',
                $emoji,
                ucfirst($error['severity'] ?? 'Error')
            ),
            'sections' => [
                [
                    'activityTitle' => $error['type'] ?? 'Unknown Error',
                    'activitySubtitle' => $errorData['timestamp_formatted'] ?? date('F j, Y g:i:s A T'),
                    'facts' => [
                        [
                            'name' => 'Severity:',
                            'value' => strtoupper($error['severity'] ?? 'ERROR')
                        ],
                        [
                            'name' => 'Message:',
                            'value' => $error['message'] ?? 'Unknown error'
                        ],
                        [
                            'name' => 'Location:',
                            'value' => sprintf(
                                '%s:%s',
                                basename($error['file'] ?? 'Unknown'),
                                $error['line'] ?? '0'
                            )
                        ],
                        [
                            'name' => 'Full Path:',
                            'value' => $error['file'] ?? 'Unknown'
                        ],
                        [
                            'name' => 'URL:',
                            'value' => $request['url'] ?? 'N/A'
                        ],
                        [
                            'name' => 'Method:',
                            'value' => $request['method'] ?? 'N/A'
                        ],
                        [
                            'name' => 'Store:',
                            'value' => sprintf(
                                '%s (%s)',
                                $store['name'] ?? 'Unknown',
                                $store['code'] ?? 'unknown'
                            )
                        ],
                        [
                            'name' => 'User:',
                            'value' => $this->formatUserInfo($user)
                        ],
                        [
                            'name' => 'IP Address:',
                            'value' => $request['ip'] ?? 'Unknown'
                        ],
                        [
                            'name' => 'User Agent:',
                            'value' => substr($request['user_agent'] ?? 'Unknown', 0, 100)
                        ],
                        [
                            'name' => 'Area:',
                            'value' => $errorData['area'] ?? 'unknown'
                        ],
                        [
                            'name' => 'Error Hash:',
                            'value' => $error['hash'] ?? 'unknown'
                        ]
                    ],
                    'markdown' => true
                ]
            ]
        ];

        // Add environment details if available
        if (isset($errorData['environment'])) {
            $env = $errorData['environment'];
            $card['sections'][] = [
                'title' => '🖥️ Environment Details',
                'facts' => [
                    [
                        'name' => 'PHP Version:',
                        'value' => $env['php_version'] ?? 'Unknown'
                    ],
                    [
                        'name' => 'Memory Usage:',
                        'value' => $env['memory_usage'] ?? 'Unknown'
                    ],
                    [
                        'name' => 'Memory Peak:',
                        'value' => $env['memory_peak'] ?? 'Unknown'
                    ],
                    [
                        'name' => 'Memory Limit:',
                        'value' => $env['memory_limit'] ?? 'Unknown'
                    ]
                ]
            ];
        }

        // Add action button to view store
        if (isset($store['base_url']) && !empty($store['base_url'])) {
            $card['potentialAction'] = [
                [
                    '@type' => 'OpenUri',
                    'name' => 'View Store',
                    'targets' => [
                        [
                            'os' => 'default',
                            'uri' => $store['base_url']
                        ]
                    ]
                ]
            ];
        }

        return $card;
    }

    /**
     * Format user information for display
     *
     * @param array{
     *     type: string,
     *     id: int|null,
     *     name: string|null,
     *     email: string|null
     * } $user
     * @return string
     */
    private function formatUserInfo(array $user): string
    {
        $type = $user['type'] ?? 'guest';

        if ($type === 'guest') {
            return 'Guest';
        }

        return sprintf(
            '%s (#%s) - %s',
            $user['name'] ?? 'Unknown',
            $user['id'] ?? 'N/A',
            $user['email'] ?? 'N/A'
        );
    }
}
