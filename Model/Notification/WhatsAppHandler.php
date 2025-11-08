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
 * WhatsApp notification handler
 *
 * Sends error notifications to WhatsApp via WhatsApp Business Cloud API
 * Uses PSR-18 HTTP Client for making HTTP requests
 */
class WhatsAppHandler implements NotificationHandlerInterface
{
    private const API_VERSION = 'v18.0';
    private const API_BASE_URL = 'https://graph.facebook.com';

    /**
     * @param ConfigInterface $config Configuration service for WhatsApp settings
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
            $accessToken = $this->getAccessToken();
            $phoneNumberId = $this->getPhoneNumberId();
            $recipientPhone = $this->getRecipientPhone();

            if (empty($accessToken) || empty($phoneNumberId) || empty($recipientPhone)) {
                $this->logger->warning('WhatsApp access token, phone number ID, or recipient phone is not configured');
                return false;
            }

            $apiUrl = sprintf(
                '%s/%s/%s/messages',
                self::API_BASE_URL,
                self::API_VERSION,
                $phoneNumberId
            );

            $payload = $this->buildWhatsAppPayload($errorData, $recipientPhone);
            $jsonPayload = $this->json->serialize($payload);

            // Create PSR-7 request with JSON payload
            $request = $this->requestFactory
                ->createRequest('POST', $apiUrl)
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('Authorization', 'Bearer ' . $accessToken)
                ->withBody($this->streamFactory->createStream($jsonPayload));

            // Send request using PSR-18 HTTP client
            $response = $this->httpClient->sendRequest($request);

            $statusCode = $response->getStatusCode();
            $responseBody = (string) $response->getBody();

            if ($statusCode === 200) {
                return true;
            }

            $this->logger->error('WhatsApp API returned non-200 status', [
                'status_code' => $statusCode,
                'response' => $responseBody
            ]);

            return false;
        } catch (ClientExceptionInterface $e) {
            $this->logger->error('Failed to send WhatsApp notification due to HTTP client error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send WhatsApp notification', [
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
        return $this->config->isWhatsAppEnabled();
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return 'WhatsApp';
    }

    /**
     * {@inheritDoc}
     */
    public function shouldHandle(array $errorData): bool
    {
        // Check severity level using centralized severity filter
        $severity = $errorData['error']['severity'] ?? 'error';
        $minSeverity = $this->config->getWhatsAppMinimumSeverity();

        return $this->severityFilter->meetsMinimumSeverity($severity, $minSeverity);
    }

    /**
     * Get WhatsApp access token from configuration
     *
     * @return string
     */
    private function getAccessToken(): string
    {
        return $this->config->getWhatsAppAccessToken();
    }

    /**
     * Get WhatsApp phone number ID from configuration
     *
     * @return string
     */
    private function getPhoneNumberId(): string
    {
        return $this->config->getWhatsAppPhoneNumberId();
    }

    /**
     * Get recipient phone number from configuration
     *
     * @return string
     */
    private function getRecipientPhone(): string
    {
        return $this->config->getWhatsAppRecipientPhone();
    }

    /**
     * Build WhatsApp message payload
     *
     * Uses WhatsApp Business Cloud API message format
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
     * @param string $recipientPhone
     * @return array{
     *     messaging_product: string,
     *     to: string,
     *     type: string,
     *     text: array{
     *         body: string
     *     }
     * }
     */
    private function buildWhatsAppPayload(array $errorData, string $recipientPhone): array
    {
        $error = $errorData['error'] ?? [];
        $request = $errorData['request'] ?? [];
        $store = $errorData['frontend_store'] ?? [];
        $user = $errorData['user'] ?? [];

        // Emoji based on severity
        $emoji = match ($error['severity'] ?? 'error') {
            'critical' => '🚨',
            'error' => '⚠️',
            'warning' => '🔶',
            default => '❓'
        };

        // Build message text (WhatsApp has 4096 character limit)
        $text = sprintf(
            "%s *%s Error Detected*\n\n",
            $emoji,
            strtoupper($error['severity'] ?? 'Error')
        );

        // Error details
        $text .= sprintf("*Type:* %s\n", $error['type'] ?? 'Unknown');
        $text .= sprintf("*Severity:* %s\n", strtoupper($error['severity'] ?? 'ERROR'));
        $text .= sprintf("*Message:* %s\n\n", $this->truncate($error['message'] ?? 'Unknown error', 200));

        // Location information
        $text .= sprintf(
            "*Location:* %s:%s\n",
            basename($error['file'] ?? 'Unknown'),
            $error['line'] ?? '0'
        );
        $text .= sprintf("*Full Path:* %s\n\n", $this->truncate($error['file'] ?? 'Unknown', 100));

        // Request information
        $text .= sprintf("*URL:* %s\n", $this->truncate($request['url'] ?? 'N/A', 100));
        $text .= sprintf("*Method:* %s\n", $request['method'] ?? 'N/A');
        $text .= sprintf(
            "*Store:* %s (%s)\n",
            $store['name'] ?? 'Unknown',
            $store['code'] ?? 'unknown'
        );

        // User information
        $text .= sprintf("*User:* %s\n", $this->formatUserInfo($user));
        $text .= sprintf("*IP Address:* %s\n", $request['ip'] ?? 'Unknown');

        // Area and timestamp
        $text .= sprintf("*Area:* %s\n", $errorData['area'] ?? 'unknown');
        $text .= sprintf(
            "*Timestamp:* %s\n",
            $errorData['timestamp_formatted'] ?? date('Y-m-d H:i:s')
        );

        // Environment information if available
        if (isset($errorData['environment'])) {
            $env = $errorData['environment'];
            $text .= sprintf(
                "\n*🖥 Environment:*\n" .
                "PHP: %s | Memory: %s / %s\n",
                $env['php_version'] ?? 'Unknown',
                $env['memory_usage'] ?? 'Unknown',
                $env['memory_limit'] ?? 'Unknown'
            );
        }

        // Footer with error hash
        $text .= sprintf(
            "\n_Error Hash: %s_",
            $error['hash'] ?? 'unknown'
        );

        // Ensure message doesn't exceed WhatsApp's 4096 character limit
        if (strlen($text) > 4096) {
            $text = substr($text, 0, 4090) . '...';
        }

        return [
            'messaging_product' => 'whatsapp',
            'to' => $recipientPhone,
            'type' => 'text',
            'text' => [
                'body' => $text
            ]
        ];
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

    /**
     * Truncate string to specified length
     *
     * @param string $text
     * @param int $maxLength
     * @return string
     */
    private function truncate(string $text, int $maxLength): string
    {
        if (strlen($text) <= $maxLength) {
            return $text;
        }

        return substr($text, 0, $maxLength - 3) . '...';
    }
}
