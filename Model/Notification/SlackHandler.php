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
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Slack notification handler
 *
 * Sends error notifications to Slack via incoming webhook using rich message formatting
 * Uses PSR-18 HTTP Client for making HTTP requests
 */
class SlackHandler implements NotificationHandlerInterface
{
    /**
     * @param ConfigInterface $config Configuration service for Slack settings
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
                $this->logger->warning('Slack webhook URL is not configured');
                return false;
            }

            $payload = $this->buildSlackPayload($errorData);
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

            $this->logger->error('Slack webhook returned non-200 status', [
                'status_code' => $statusCode,
                'response' => $responseBody
            ]);

            return false;
        } catch (ClientExceptionInterface $e) {
            $this->logger->error('Failed to send Slack notification due to HTTP client error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send Slack notification', [
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
        return $this->config->isSlackEnabled();
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return 'Slack';
    }

    /**
     * {@inheritDoc}
     */
    public function shouldHandle(array $errorData): bool
    {
        // Check severity level using centralized severity filter
        $severity = $errorData['error']['severity'] ?? 'error';
        $minSeverity = $this->config->getSlackMinimumSeverity();

        return $this->severityFilter->meetsMinimumSeverity($severity, $minSeverity);
    }

    /**
     * Get Slack webhook URL from configuration
     *
     * @return string
     */
    private function getWebhookUrl(): string
    {
        return $this->config->getSlackWebhookUrl();
    }

    /**
     * Build Slack message payload with rich formatting
     *
     * Uses Slack's Block Kit for modern, interactive messages
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
     *     text: string,
     *     attachments: array<int, array{
     *         color: string,
     *         fallback: string,
     *         fields: array<int, array{
     *             title: string,
     *             value: string,
     *             short: bool
     *         }>,
     *         footer: string,
     *         ts: int
     *     }>,
     *     channel?: string,
     *     username?: string
     * }
     */
    private function buildSlackPayload(array $errorData): array
    {
        $error = $errorData['error'] ?? [];
        $request = $errorData['request'] ?? [];
        $store = $errorData['frontend_store'] ?? [];
        $user = $errorData['user'] ?? [];

        // Determine color based on severity
        $color = match ($error['severity'] ?? 'error') {
            'critical' => 'danger',   // Red
            'error' => 'warning',     // Orange/Yellow
            'warning' => '#ffcc00',   // Yellow
            default => '#808080'      // Gray
        };

        // Emoji based on severity
        $emoji = match ($error['severity'] ?? 'error') {
            'critical' => ':rotating_light:',
            'error' => ':warning:',
            'warning' => ':large_orange_diamond:',
            default => ':grey_question:'
        };

        // Build main message
        $text = sprintf(
            '%s *%s Error Detected*',
            $emoji,
            ucfirst($error['severity'] ?? 'Error')
        );

        // Build attachment with detailed information
        $attachment = [
            'color' => $color,
            'fallback' => sprintf(
                '%s: %s',
                $error['type'] ?? 'Error',
                $error['message'] ?? 'Unknown error'
            ),
            'fields' => [
                [
                    'title' => 'Error Type',
                    'value' => $error['type'] ?? 'Unknown',
                    'short' => true
                ],
                [
                    'title' => 'Severity',
                    'value' => strtoupper($error['severity'] ?? 'ERROR'),
                    'short' => true
                ],
                [
                    'title' => 'Message',
                    'value' => '```' . ($error['message'] ?? 'Unknown error') . '```',
                    'short' => false
                ],
                [
                    'title' => 'Location',
                    'value' => sprintf(
                        '`%s:%s`',
                        basename($error['file'] ?? 'Unknown'),
                        $error['line'] ?? '0'
                    ),
                    'short' => false
                ],
                [
                    'title' => 'Full Path',
                    'value' => '```' . ($error['file'] ?? 'Unknown') . '```',
                    'short' => false
                ],
                [
                    'title' => 'URL',
                    'value' => $request['url'] ?? 'N/A',
                    'short' => false
                ],
                [
                    'title' => 'Method',
                    'value' => $request['method'] ?? 'N/A',
                    'short' => true
                ],
                [
                    'title' => 'Store',
                    'value' => sprintf(
                        '%s (%s)',
                        $store['name'] ?? 'Unknown',
                        $store['code'] ?? 'unknown'
                    ),
                    'short' => true
                ],
                [
                    'title' => 'User',
                    'value' => $this->formatUserInfo($user),
                    'short' => true
                ],
                [
                    'title' => 'IP Address',
                    'value' => $request['ip'] ?? 'Unknown',
                    'short' => true
                ],
                [
                    'title' => 'Timestamp',
                    'value' => $errorData['timestamp_formatted'] ?? date('Y-m-d H:i:s'),
                    'short' => true
                ]
            ],
            'footer' => sprintf(
                'Error Hash: %s | Area: %s',
                $error['hash'] ?? 'unknown',
                $errorData['area'] ?? 'unknown'
            ),
            'ts' => time()
        ];

        // Add environment info if available
        if (isset($errorData['environment'])) {
            $env = $errorData['environment'];
            $attachment['fields'][] = [
                'title' => 'Environment',
                'value' => sprintf(
                    'PHP: %s | Memory: %s / %s',
                    $env['php_version'] ?? 'Unknown',
                    $env['memory_usage'] ?? 'Unknown',
                    $env['memory_limit'] ?? 'Unknown'
                ),
                'short' => false
            ];
        }

        // Build payload
        $payload = [
            'text' => $text,
            'attachments' => [$attachment]
        ];

        // Add channel if configured
        $channel = $this->config->getSlackChannel();
        if (!empty($channel)) {
            $payload['channel'] = $channel;
        }

        // Add username if configured
        $username = $this->config->getSlackUsername();
        if (!empty($username)) {
            $payload['username'] = $username;
        }

        return $payload;
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
            '%s (#%s)',
            $user['name'] ?? $user['email'] ?? 'Unknown',
            $user['id'] ?? 'N/A'
        );
    }
}
