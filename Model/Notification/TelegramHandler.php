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
 * Telegram notification handler
 *
 * Sends error notifications to Telegram via Bot API using sendMessage method
 * Uses PSR-18 HTTP Client for making HTTP requests
 */
class TelegramHandler implements NotificationHandlerInterface
{
    /**
     * @param ConfigInterface $config Configuration service for Telegram settings
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
            $botToken = $this->getBotToken();
            $chatId = $this->getChatId();

            if (empty($botToken) || empty($chatId)) {
                $this->logger->warning('Telegram bot token or chat ID is not configured');
                return false;
            }

            $apiUrl = sprintf('https://api.telegram.org/bot%s/sendMessage', $botToken);
            $payload = $this->buildTelegramPayload($errorData, $chatId);
            $jsonPayload = $this->json->serialize($payload);

            // Create PSR-7 request with JSON payload
            $request = $this->requestFactory
                ->createRequest('POST', $apiUrl)
                ->withHeader('Content-Type', 'application/json')
                ->withBody($this->streamFactory->createStream($jsonPayload));

            // Send request using PSR-18 HTTP client
            $response = $this->httpClient->sendRequest($request);

            $statusCode = $response->getStatusCode();
            $responseBody = (string) $response->getBody();

            if ($statusCode === 200) {
                return true;
            }

            $this->logger->error('Telegram API returned non-200 status', [
                'status_code' => $statusCode,
                'response' => $responseBody
            ]);

            return false;
        } catch (ClientExceptionInterface $e) {
            $this->logger->error('Failed to send Telegram notification due to HTTP client error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send Telegram notification', [
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
        return $this->config->isTelegramEnabled();
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return 'Telegram';
    }

    /**
     * {@inheritDoc}
     */
    public function shouldHandle(array $errorData): bool
    {
        // Check severity level using centralized severity filter
        $severity = $errorData['error']['severity'] ?? 'error';
        $minSeverity = $this->config->getTelegramMinimumSeverity();

        return $this->severityFilter->meetsMinimumSeverity($severity, $minSeverity);
    }

    /**
     * Get Telegram bot token from configuration
     *
     * @return string
     */
    private function getBotToken(): string
    {
        return $this->config->getTelegramBotToken();
    }

    /**
     * Get Telegram chat ID from configuration
     *
     * @return string
     */
    private function getChatId(): string
    {
        return $this->config->getTelegramChatId();
    }

    /**
     * Build Telegram message payload with HTML formatting
     *
     * Uses Telegram Bot API sendMessage method with HTML parse_mode
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
     * @param string $chatId
     * @return array{
     *     chat_id: string,
     *     text: string,
     *     parse_mode: string,
     *     disable_web_page_preview: bool
     * }
     */
    private function buildTelegramPayload(array $errorData, string $chatId): array
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

        // Build message text with HTML formatting
        $text = sprintf(
            "%s <b>%s Error Detected</b>\n\n",
            $emoji,
            ucfirst($error['severity'] ?? 'Error')
        );

        // Error details
        $text .= sprintf("<b>Type:</b> %s\n", $this->escapeHtml($error['type'] ?? 'Unknown'));
        $text .= sprintf("<b>Severity:</b> %s\n", strtoupper($error['severity'] ?? 'ERROR'));
        $text .= sprintf("<b>Message:</b> %s\n\n", $this->escapeHtml($error['message'] ?? 'Unknown error'));

        // Location information
        $text .= sprintf(
            "<b>Location:</b> %s:%s\n",
            $this->escapeHtml(basename($error['file'] ?? 'Unknown')),
            $error['line'] ?? '0'
        );
        $text .= sprintf("<b>Full Path:</b> <code>%s</code>\n\n", $this->escapeHtml($error['file'] ?? 'Unknown'));

        // Request information
        $text .= sprintf("<b>URL:</b> %s\n", $this->escapeHtml($request['url'] ?? 'N/A'));
        $text .= sprintf("<b>Method:</b> %s\n", $this->escapeHtml($request['method'] ?? 'N/A'));
        $text .= sprintf(
            "<b>Store:</b> %s (%s)\n",
            $this->escapeHtml($store['name'] ?? 'Unknown'),
            $this->escapeHtml($store['code'] ?? 'unknown')
        );

        // User information
        $text .= sprintf("<b>User:</b> %s\n", $this->escapeHtml($this->formatUserInfo($user)));
        $text .= sprintf("<b>IP Address:</b> %s\n", $this->escapeHtml($request['ip'] ?? 'Unknown'));

        // Area and timestamp
        $text .= sprintf("<b>Area:</b> %s\n", $this->escapeHtml($errorData['area'] ?? 'unknown'));
        $text .= sprintf(
            "<b>Timestamp:</b> %s\n",
            $this->escapeHtml($errorData['timestamp_formatted'] ?? date('Y-m-d H:i:s'))
        );

        // Environment information if available
        if (isset($errorData['environment'])) {
            $env = $errorData['environment'];
            $text .= sprintf(
                "\n<b>🖥 Environment:</b>\n" .
                "PHP: %s | Memory: %s / %s\n",
                $this->escapeHtml($env['php_version'] ?? 'Unknown'),
                $this->escapeHtml($env['memory_usage'] ?? 'Unknown'),
                $this->escapeHtml($env['memory_limit'] ?? 'Unknown')
            );
        }

        // Footer with error hash
        $text .= sprintf(
            "\n<i>Error Hash: %s</i>",
            $this->escapeHtml($error['hash'] ?? 'unknown')
        );

        return [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true
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
     * Escape HTML special characters for Telegram HTML parse mode
     *
     * @param string $text
     * @return string
     */
    private function escapeHtml(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
