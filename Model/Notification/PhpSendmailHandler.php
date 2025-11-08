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
use Psr\Log\LoggerInterface;

/**
 * PHP Sendmail handler
 *
 * Uses native PHP mail() function to send emails.
 * Typically configured as fallback for MagentoMailHandler via di.xml
 */
class PhpSendmailHandler implements NotificationHandlerInterface
{
    /**
     * @param ConfigInterface $config Configuration service for email settings
     * @param LoggerInterface $logger Logger for error tracking
     * @param SeverityFilterInterface $severityFilter Centralized severity checking service
     */
    public function __construct(
        private readonly ConfigInterface $config,
        private readonly LoggerInterface $logger,
        private readonly SeverityFilterInterface $severityFilter
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function send(array $errorData): bool
    {
        $result = true;

        // Send to developers
        $developerEmails = $this->parseEmails($this->config->getDeveloperEmails());
        if (!empty($developerEmails)) {
            $result = $this->sendEmail($errorData, $developerEmails, 'developer') && $result;
        }

        // Send to clients if enabled
        if ($this->config->isClientNotificationEnabled()) {
            $clientEmails = $this->parseEmails($this->config->getClientEmails());
            if (!empty($clientEmails)) {
                $result = $this->sendEmail($errorData, $clientEmails, 'client') && $result;
            }
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function isEnabled(): bool
    {
        // Enabled when module and email notifications are enabled, and function exists
        return $this->config->isEnabled() &&
               $this->config->isEmailEnabled() &&
               function_exists('mail');
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return 'PHP Sendmail';
    }

    /**
     * {@inheritDoc}
     */
    public function shouldHandle(array $errorData): bool
    {
        // Always handle because this is a fallback handler
        return true;
    }

    /**
     * Send email using native PHP mail() function
     *
     * @param array<string, mixed> $errorData
     * @param array<int, string> $emails
     * @param string $type Either 'developer' or 'client'
     * @return bool
     */
    private function sendEmail(array $errorData, array $emails, string $type): bool
    {
        try {
            $storeName = $errorData['frontend_store']['name'] ?? 'Error Reporting System';
            $serverName = $_SERVER['SERVER_NAME'] ?? 'localhost';

            // Build email subject
            $errorType = $errorData['error']['type'] ?? 'Unknown Error';
            $severity = strtoupper($errorData['error']['severity'] ?? 'ERROR');
            $subject = "[$severity] $errorType on $storeName";

            // Build email body
            $body = $type === 'developer'
                ? $this->buildDeveloperEmailBody($errorData)
                : $this->buildClientEmailBody($errorData);

            // Build headers
            $headers = [
                'From: ' . $storeName . ' <no-reply@' . $serverName . '>',
                'Reply-To: no-reply@' . $serverName,
                'X-Mailer: PHP/' . phpversion(),
                'MIME-Version: 1.0',
                'Content-Type: text/html; charset=UTF-8'
            ];

            // Send to each recipient
            $success = true;
            foreach ($emails as $email) {
                $result = mail($email, $subject, $body, implode("\r\n", $headers));
                if (!$result) {
                    $this->logger->error('Failed to send email via PHP mail()', [
                        'recipient' => $email,
                        'type' => $type
                    ]);
                    $success = false;
                } else {
                    $this->logger->info('Email sent via PHP Sendmail (fallback)', [
                        'recipient' => $email,
                        'type' => $type,
                        'error_hash' => $errorData['error']['hash'] ?? 'unknown'
                    ]);
                }
            }

            return $success;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send email via PHP mail()', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Build HTML email body for developers
     *
     * @param array<string, mixed> $errorData
     * @return string
     */
    private function buildDeveloperEmailBody(array $errorData): string
    {
        $error = $errorData['error'] ?? [];
        $request = $errorData['request'] ?? [];
        $store = $errorData['frontend_store'] ?? [];
        $storeName = $store['name'] ?? 'Website';
        $timestamp = $errorData['timestamp_formatted'] ?? date('F j, Y g:i:s A T');

        $html = '<!DOCTYPE html>';
        $html .= '<html><head><meta charset="UTF-8"></head>';
        $html .= '<body style="font-family: Arial, sans-serif; background-color: #f4f4f4; padding: 20px;">';
        $html .= '<div style="max-width: 600px; margin: 0 auto; background-color: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">';

        // Header
        $html .= '<div style="background-color: #d32f2f; color: #fff; padding: 30px 20px; text-align: center;">';
        $html .= '<h1 style="margin: 0; font-size: 24px;">⚠️ Application Error</h1>';
        $html .= '<p style="margin: 10px 0 0 0; opacity: 0.9;">Sent via PHP Sendmail (Fallback)</p>';
        $html .= '</div>';

        // Body
        $html .= '<div style="padding: 30px 20px;">';
        $html .= '<table style="width: 100%; border-collapse: collapse;">';
        $html .= '<tr><td style="padding: 8px; background-color: #f8f9fa; font-weight: bold; width: 30%;">Error Type:</td>';
        $html .= '<td style="padding: 8px;">' . htmlspecialchars($error['type'] ?? 'Unknown') . '</td></tr>';
        $html .= '<tr><td style="padding: 8px; background-color: #f8f9fa; font-weight: bold;">Message:</td>';
        $html .= '<td style="padding: 8px; color: #d32f2f;"><strong>' . htmlspecialchars($error['message'] ?? 'No message') . '</strong></td></tr>';
        $html .= '<tr><td style="padding: 8px; background-color: #f8f9fa; font-weight: bold;">File:</td>';
        $html .= '<td style="padding: 8px; font-family: monospace; font-size: 12px;">' . htmlspecialchars($error['file'] ?? 'Unknown') . '</td></tr>';
        $html .= '<tr><td style="padding: 8px; background-color: #f8f9fa; font-weight: bold;">Line:</td>';
        $html .= '<td style="padding: 8px;">' . htmlspecialchars((string)($error['line'] ?? '')) . '</td></tr>';
        $html .= '<tr><td style="padding: 8px; background-color: #f8f9fa; font-weight: bold;">URL:</td>';
        $html .= '<td style="padding: 8px; word-break: break-all;">' . htmlspecialchars($request['url'] ?? 'N/A') . '</td></tr>';
        $html .= '<tr><td style="padding: 8px; background-color: #f8f9fa; font-weight: bold;">Timestamp:</td>';
        $html .= '<td style="padding: 8px;">' . htmlspecialchars($timestamp) . '</td></tr>';
        $html .= '</table>';

        // Stack trace if available
        if (!empty($errorData['trace'])) {
            $html .= '<div style="margin-top: 20px; padding: 15px; background-color: #f8f9fa; border-radius: 4px;">';
            $html .= '<h3 style="margin: 0 0 10px 0; font-size: 16px;">Stack Trace:</h3>';
            $html .= '<pre style="margin: 0; font-size: 11px; overflow-x: auto; white-space: pre-wrap;">' . htmlspecialchars($errorData['trace']) . '</pre>';
            $html .= '</div>';
        }

        $html .= '</div>';

        // Footer
        $html .= '<div style="background-color: #f8f9fa; padding: 15px 20px; text-align: center; border-top: 1px solid #e0e0e0;">';
        $html .= '<p style="margin: 0; color: #666; font-size: 12px;">Automated alert from ' . htmlspecialchars($storeName) . '</p>';
        $html .= '</div>';

        $html .= '</div></body></html>';

        return $html;
    }

    /**
     * Build HTML email body for clients
     *
     * @param array<string, mixed> $errorData
     * @return string
     */
    private function buildClientEmailBody(array $errorData): string
    {
        $request = $errorData['request'] ?? [];
        $store = $errorData['frontend_store'] ?? [];
        $storeName = $store['name'] ?? 'Website';
        $timestamp = $errorData['timestamp_formatted'] ?? date('F j, Y g:i:s A T');

        $html = '<!DOCTYPE html>';
        $html .= '<html><head><meta charset="UTF-8"></head>';
        $html .= '<body style="font-family: Arial, sans-serif; background-color: #f4f4f4; padding: 20px;">';
        $html .= '<div style="max-width: 600px; margin: 0 auto; background-color: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">';

        // Header
        $html .= '<div style="background-color: #ff9800; color: #fff; padding: 30px 20px; text-align: center;">';
        $html .= '<h1 style="margin: 0; font-size: 24px;">Website Issue Detected</h1>';
        $html .= '<p style="margin: 10px 0 0 0; opacity: 0.9;">We\'re On It!</p>';
        $html .= '</div>';

        // Body
        $html .= '<div style="padding: 30px 20px;">';
        $html .= '<p style="margin: 0 0 20px 0; font-size: 16px;">This is an automated notification from ' . htmlspecialchars($storeName) . '.</p>';
        $html .= '<div style="background-color: #fff3cd; border-left: 4px solid #ff9800; padding: 15px; margin-bottom: 20px;">';
        $html .= '<p style="margin: 0; color: #856404;">An issue has been detected on the website and our technical team has been automatically notified.</p>';
        $html .= '</div>';

        $html .= '<table style="width: 100%; border-collapse: collapse;">';
        $html .= '<tr><td style="padding: 8px; background-color: #f8f9fa; font-weight: bold; width: 30%;">Detected At:</td>';
        $html .= '<td style="padding: 8px;">' . htmlspecialchars($timestamp) . '</td></tr>';
        $html .= '<tr><td style="padding: 8px; background-color: #f8f9fa; font-weight: bold;">Location:</td>';
        $html .= '<td style="padding: 8px; word-break: break-all;">' . htmlspecialchars($request['url'] ?? 'N/A') . '</td></tr>';
        $html .= '<tr><td style="padding: 8px; background-color: #f8f9fa; font-weight: bold;">Store:</td>';
        $html .= '<td style="padding: 8px;">' . htmlspecialchars($storeName) . '</td></tr>';
        $html .= '</table>';

        $html .= '<div style="margin-top: 20px; padding: 15px; background-color: #e3f2fd; border-left: 4px solid #2196f3;">';
        $html .= '<p style="margin: 0; color: #1565c0; font-size: 13px;"><strong>Note:</strong> Our team is working to resolve this issue. You do not need to take any action.</p>';
        $html .= '</div>';

        $html .= '</div>';

        // Footer
        $html .= '<div style="background-color: #f8f9fa; padding: 15px 20px; text-align: center; border-top: 1px solid #e0e0e0;">';
        $html .= '<p style="margin: 0; color: #666; font-size: 12px;">Automated alert from ' . htmlspecialchars($storeName) . '</p>';
        $html .= '</div>';

        $html .= '</div></body></html>';

        return $html;
    }

    /**
     * Parse comma-separated email addresses
     *
     * @param string $emailString
     * @return array<int, string>
     */
    private function parseEmails(string $emailString): array
    {
        $emails = array_map('trim', explode(',', $emailString));
        return array_filter($emails, function ($email) {
            return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
        });
    }
}
