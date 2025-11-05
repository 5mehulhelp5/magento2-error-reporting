<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */
declare(strict_types=1);

namespace Hryvinskyi\ErrorReporting\Model;

use Hryvinskyi\ErrorReporting\Api\ConfigInterface;
use Hryvinskyi\ErrorReporting\Api\EmailSenderInterface;
use Magento\Backend\App\Area\FrontNameResolver;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service for sending error notification emails
 */
class EmailSender implements EmailSenderInterface
{
    public function __construct(
        private readonly ConfigInterface $config,
        private readonly TransportBuilder $transportBuilder,
        private readonly StateInterface $inlineTranslation,
        private readonly StoreManagerInterface $storeManager,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function sendErrorNotification(array $errorData): bool
    {
        if (!$this->config->isEnabled()) {
            return false;
        }

        $result = true;

        // Send to developers - try Magento first, fallback to PHP mail()
        $developerEmails = $this->parseEmails($this->config->getDeveloperEmails());
        if (!empty($developerEmails)) {
            $result = $this->sendDeveloperEmail($errorData, $developerEmails) && $result;
        }

        // Send to clients if enabled - try Magento first, fallback to PHP mail()
        if ($this->config->isClientNotificationEnabled()) {
            $clientEmails = $this->parseEmails($this->config->getClientEmails());
            if (!empty($clientEmails)) {
                $result = $this->sendClientEmail($errorData, $clientEmails) && $result;
            }
        }

        return $result;
    }

    /**
     * Send detailed developer email
     *
     * @param array<string, mixed> $errorData
     * @param array<int, string> $emails
     * @return bool
     */
    private function sendDeveloperEmail(array $errorData, array $emails): bool
    {
        try {
            $this->inlineTranslation->suspend();
            $errorData['store'] = $this->storeManager->getStore(Store::DEFAULT_STORE_ID);
            $transport = $this->transportBuilder
                ->setTemplateIdentifier('hryvinskyi_error_reporting_developer')
                ->setTemplateOptions([
                    'area' => FrontNameResolver::AREA_CODE,
                    'store' => Store::DEFAULT_STORE_ID
                ])
                ->setTemplateVars($errorData)
                ->setFromByScope(
                    $this->config->getEmailSender(),
                    Store::DEFAULT_STORE_ID
                )
                ->addTo($emails)
                ->getTransport();

            $transport->sendMessage();
            $this->inlineTranslation->resume();

            $this->logger->info('Developer error notification sent via Magento', [
                'error_hash' => $errorData['error']['hash'] ?? 'unknown',
                'recipients' => count($emails)
            ]);

            return true;
        } catch (\Throwable $e) {
            // Catch all errors including Error and Exception
            try {
                $this->inlineTranslation->resume();
            } catch (\Throwable $ignored) {
                // Ignore if inline translation fails
            }

            $this->logger->warning('Magento email system failed, falling back to PHP mail()', [
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'trace' => $e->getTraceAsString()
            ]);

            // Fallback to native PHP mail()
            return $this->sendViaPHPMail($errorData, $emails, 'developer');
        }
    }

    /**
     * Send simplified client email
     *
     * @param array<string, mixed> $errorData
     * @param array<int, string> $emails
     * @return bool
     */
    private function sendClientEmail(array $errorData, array $emails): bool
    {
        try {
            $this->inlineTranslation->suspend();

            // Remove sensitive data for client email
            $clientData = [
                'error' => [
                    'message' => 'An issue was detected',
                    'severity' => $errorData['error']['severity'] ?? 'error',
                ],
                'request' => [
                    'url' => $errorData['request']['url'] ?? '',
                    'method' => $errorData['request']['method'] ?? '',
                ],
                'store' => $this->storeManager->getStore(Store::DEFAULT_STORE_ID),
                'timestamp_formatted' => $errorData['timestamp_formatted'] ?? '',
                'frontend_store' => $errorData['frontend_store'] ?? false,
            ];

            $transport = $this->transportBuilder
                ->setTemplateIdentifier('hryvinskyi_error_reporting_client')
                ->setTemplateOptions([
                    'area' => FrontNameResolver::AREA_CODE,
                    'store' => Store::DEFAULT_STORE_ID
                ])
                ->setTemplateVars($clientData)
                ->setFromByScope(
                    $this->config->getEmailSender(),
                    Store::DEFAULT_STORE_ID
                )
                ->addTo($emails)
                ->getTransport();

            $transport->sendMessage();
            $this->inlineTranslation->resume();

            $this->logger->info('Client error notification sent via Magento', [
                'error_hash' => $errorData['error']['hash'] ?? 'unknown',
                'recipients' => count($emails)
            ]);

            return true;
        } catch (\Throwable $e) {
            // Catch all errors including Error and Exception
            try {
                $this->inlineTranslation->resume();
            } catch (\Throwable $ignored) {
                // Ignore if inline translation fails
            }

            $this->logger->warning('Magento email system failed, falling back to PHP mail()', [
                'error' => $e->getMessage(),
                'error_class' => get_class($e)
            ]);

            // Fallback to native PHP mail()
            return $this->sendViaPHPMail($errorData, $emails, 'client');
        }
    }

    /**
     * Send email using native PHP mail() function (fallback when Magento fails)
     *
     * @param array<string, mixed> $errorData
     * @param array<int, string> $emails
     * @param string $type Either 'developer' or 'client'
     * @return bool
     */
    private function sendViaPHPMail(array $errorData, array $emails, string $type): bool
    {
        try {
            $storeName = $errorData['store']['name'] ?? 'Error Reporting System';

            // Build email subject
            $errorType = $errorData['error']['type'] ?? 'Unknown Error';
            $severity = strtoupper($errorData['error']['severity'] ?? 'ERROR');
            $subject = "[$severity] $errorType on $storeName";

            // Build email body based on type
            if ($type === 'developer') {
                $body = $this->buildDeveloperEmailBody($errorData);
            } else {
                $body = $this->buildClientEmailBody($errorData);
            }

            // Build headers
            $headers = [
                'From: ' . $storeName . ' <no-reply@' . ($_SERVER['SERVER_NAME'] ?? 'localhost') . '>',
                'Reply-To: no-reply@' . ($_SERVER['SERVER_NAME'] ?? 'localhost'),
                'X-Mailer: PHP/' . phpversion(),
                'MIME-Version: 1.0',
                'Content-Type: text/html; charset=UTF-8'
            ];

            // Send to each recipient
            $success = true;
            foreach ($emails as $email) {
                $result = mail($email, $subject, $body, implode("\r\n", $headers));
                if (!$result) {
                    $this->logger->error('Failed to send email via PHP mail()', ['recipient' => $email]);
                    $success = false;
                } else {
                    $this->logger->info('Email sent via PHP mail()', [
                        'recipient' => $email,
                        'type' => $type,
                        'error_hash' => $errorData['error']['hash'] ?? 'unknown'
                    ]);
                }
            }

            return $success;
        } catch (\Exception $e) {
            $this->logger->error('Failed to send email via PHP mail()', [
                'error' => $e->getMessage()
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
        $client = $errorData['client'] ?? [];
        $store = $errorData['store'] ?? [];
        $environment = $errorData['environment'] ?? [];
        $area = $errorData['area'] ?? 'unknown';

        $html = '<!DOCTYPE html>';
        $html .= '<html>';
        $html .= '<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>';
        $html .= '<body style="margin: 0; padding: 0; background-color: #f4f4f4; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;">';
        $html .= '<table role="presentation" style="width: 100%; border-collapse: collapse; background-color: #f4f4f4; padding: 40px 0;">';
        $html .= '<tr><td align="center">';
        $html .= '<table role="presentation" style="width: 600px; border-collapse: collapse; background-color: #ffffff; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">';

        // Header
        $html .= '<tr><td style="background: linear-gradient(135deg, #f44336 0%, #d32f2f 100%); padding: 40px 30px; text-align: center; border-radius: 8px 8px 0 0;">';
        $html .= '<div style="background-color: rgba(255,255,255,0.2); border-radius: 50%; width: 80px; height: 80px; margin: 0 auto 20px; display: inline-block; line-height: 80px;">';
        $html .= '<span style="font-size: 48px;">⚠️</span></div>';
        $html .= '<h1 style="margin: 0; color: #ffffff; font-size: 28px; font-weight: 600;">Application Error Detected</h1>';
        $html .= '<p style="margin: 10px 0 0 0; color: #ffffff; opacity: 0.9; font-size: 16px;">Severity: <strong style="text-transform: uppercase;">' . htmlspecialchars($error['severity'] ?? 'error') . '</strong></p>';
        $html .= '</td></tr>';

        // Body
        $html .= '<tr><td style="padding: 40px 30px;">';

        // Alert Message
        $html .= '<div style="background-color: #fff3cd; border-left: 4px solid #ff9800; padding: 16px 20px; margin-bottom: 30px; border-radius: 4px;">';
        $html .= '<p style="margin: 0; color: #856404; font-size: 16px; font-weight: 500;">⚠ An error has occurred on your website</p></div>';

        $html .= '<p style="color: #333; font-size: 16px; line-height: 1.6; margin: 0 0 30px 0;">This is an automated alert from the ' . htmlspecialchars($store['name'] ?? 'website') . ' error monitoring system.</p>';

        // Error Details Table
        $html .= '<table style="width: 100%; border-collapse: collapse; margin-bottom: 30px;">';
        $html .= '<tr><td colspan="2" style="padding: 12px 16px; background-color: #f44336; color: #ffffff; font-size: 16px; font-weight: 600;">Error Details</td></tr>';
        $html .= '<tr><td style="padding: 12px 16px; background-color: #f8f9fa; border-bottom: 1px solid #e9ecef; color: #666; font-size: 14px; width: 35%;"><strong>Error Type:</strong></td>';
        $html .= '<td style="padding: 12px 16px; background-color: #ffffff; border-bottom: 1px solid #e9ecef; color: #333; font-size: 14px; word-break: break-word;">' . htmlspecialchars($error['type'] ?? '') . '</td></tr>';
        $html .= '<tr><td style="padding: 12px 16px; background-color: #f8f9fa; border-bottom: 1px solid #e9ecef; color: #666; font-size: 14px;"><strong>Message:</strong></td>';
        $html .= '<td style="padding: 12px 16px; background-color: #ffffff; border-bottom: 1px solid #e9ecef; color: #d32f2f; font-size: 14px; word-break: break-word;"><strong>' . htmlspecialchars($error['message'] ?? '') . '</strong></td></tr>';
        $html .= '<tr><td style="padding: 12px 16px; background-color: #f8f9fa; border-bottom: 1px solid #e9ecef; color: #666; font-size: 14px;"><strong>File:</strong></td>';
        $html .= '<td style="padding: 12px 16px; background-color: #ffffff; border-bottom: 1px solid #e9ecef; color: #333; font-size: 13px; font-family: \'Courier New\', monospace; word-break: break-all;">' . htmlspecialchars($error['file'] ?? '') . '</td></tr>';
        $html .= '<tr><td style="padding: 12px 16px; background-color: #f8f9fa; border-bottom: 1px solid #e9ecef; color: #666; font-size: 14px;"><strong>Line:</strong></td>';
        $html .= '<td style="padding: 12px 16px; background-color: #ffffff; border-bottom: 1px solid #e9ecef; color: #333; font-size: 14px;">' . htmlspecialchars((string)($error['line'] ?? '')) . '</td></tr>';
        $html .= '<tr><td style="padding: 12px 16px; background-color: #f8f9fa; border-bottom: 1px solid #e9ecef; color: #666; font-size: 14px;"><strong>Timestamp:</strong></td>';
        $html .= '<td style="padding: 12px 16px; background-color: #ffffff; border-bottom: 1px solid #e9ecef; color: #333; font-size: 14px;">' . htmlspecialchars($errorData['timestamp_formatted'] ?? '') . '</td></tr>';
        $html .= '<tr><td style="padding: 12px 16px; background-color: #f8f9fa; color: #666; font-size: 14px;"><strong>Error Hash:</strong></td>';
        $html .= '<td style="padding: 12px 16px; background-color: #ffffff; color: #666; font-size: 11px; font-family: \'Courier New\', monospace; word-break: break-all;">' . htmlspecialchars($error['hash'] ?? '') . '</td></tr>';
        $html .= '</table>';

        // Request Information Table
        $html .= '<table style="width: 100%; border-collapse: collapse; margin-bottom: 30px;">';
        $html .= '<tr><td colspan="2" style="padding: 12px 16px; background-color: #2196f3; color: #ffffff; font-size: 16px; font-weight: 600;">Request Information</td></tr>';
        $html .= '<tr><td style="padding: 12px 16px; background-color: #f8f9fa; border-bottom: 1px solid #e9ecef; color: #666; font-size: 14px; width: 35%;"><strong>URL:</strong></td>';
        $html .= '<td style="padding: 12px 16px; background-color: #ffffff; border-bottom: 1px solid #e9ecef; color: #2196f3; font-size: 13px; word-break: break-all;"><a href="' . htmlspecialchars($request['url'] ?? '') . '" style="color: #2196f3; text-decoration: none;">' . htmlspecialchars($request['url'] ?? '') . '</a></td></tr>';
        $html .= '<tr><td style="padding: 12px 16px; background-color: #f8f9fa; border-bottom: 1px solid #e9ecef; color: #666; font-size: 14px;"><strong>Method:</strong></td>';
        $html .= '<td style="padding: 12px 16px; background-color: #ffffff; border-bottom: 1px solid #e9ecef; color: #333; font-size: 14px;">' . htmlspecialchars($request['method'] ?? '') . '</td></tr>';
        $html .= '<tr><td style="padding: 12px 16px; background-color: #f8f9fa; border-bottom: 1px solid #e9ecef; color: #666; font-size: 14px;"><strong>Area:</strong></td>';
        $html .= '<td style="padding: 12px 16px; background-color: #ffffff; border-bottom: 1px solid #e9ecef; color: #333; font-size: 14px;">' . htmlspecialchars($area) . '</td></tr>';
        $html .= '<tr><td style="padding: 12px 16px; background-color: #f8f9fa; border-bottom: 1px solid #e9ecef; color: #666; font-size: 14px;"><strong>Store:</strong></td>';
        $html .= '<td style="padding: 12px 16px; background-color: #ffffff; border-bottom: 1px solid #e9ecef; color: #333; font-size: 14px;">' . htmlspecialchars($store['name'] ?? '') . ' (' . htmlspecialchars($store['code'] ?? '') . ')</td></tr>';
        $html .= '<tr><td style="padding: 12px 16px; background-color: #f8f9fa; color: #666; font-size: 14px;"><strong>Client IP:</strong></td>';
        $html .= '<td style="padding: 12px 16px; background-color: #ffffff; color: #333; font-size: 14px;">' . htmlspecialchars($client['ip'] ?? '') . '</td></tr>';
        $html .= '</table>';

        // Stack Trace
        if (isset($errorData['trace'])) {
            $html .= '<div style="background-color: #f8f9fa; padding: 24px; border-radius: 6px; margin-bottom: 20px;">';
            $html .= '<h2 style="margin: 0 0 16px 0; color: #333; font-size: 18px; font-weight: 600;">Stack Trace:</h2>';
            $html .= '<div style="background-color: #ffffff; border: 1px solid #e9ecef; border-radius: 4px; padding: 16px; overflow-x: auto;">';
            $html .= '<pre style="margin: 0; font-family: \'Courier New\', monospace; font-size: 12px; line-height: 1.5; color: #333; white-space: pre-wrap; word-wrap: break-word;">' . htmlspecialchars($errorData['trace']) . '</pre>';
            $html .= '</div></div>';
        }

        // Environment Info
        if (!empty($environment['php_version'])) {
            $html .= '<table style="width: 100%; border-collapse: collapse; margin-bottom: 30px;">';
            $html .= '<tr><td colspan="2" style="padding: 12px 16px; background-color: #9c27b0; color: #ffffff; font-size: 16px; font-weight: 600;">Server Environment</td></tr>';
            $html .= '<tr><td style="padding: 12px 16px; background-color: #f8f9fa; border-bottom: 1px solid #e9ecef; color: #666; font-size: 14px; width: 35%;"><strong>PHP Version:</strong></td>';
            $html .= '<td style="padding: 12px 16px; background-color: #ffffff; border-bottom: 1px solid #e9ecef; color: #333; font-size: 14px;">' . htmlspecialchars($environment['php_version'] ?? '') . '</td></tr>';
            $html .= '<tr><td style="padding: 12px 16px; background-color: #f8f9fa; border-bottom: 1px solid #e9ecef; color: #666; font-size: 14px;"><strong>Memory Usage:</strong></td>';
            $html .= '<td style="padding: 12px 16px; background-color: #ffffff; border-bottom: 1px solid #e9ecef; color: #333; font-size: 14px;">' . htmlspecialchars($environment['memory_usage'] ?? '') . '</td></tr>';
            $html .= '<tr><td style="padding: 12px 16px; background-color: #f8f9fa; border-bottom: 1px solid #e9ecef; color: #666; font-size: 14px;"><strong>Memory Peak:</strong></td>';
            $html .= '<td style="padding: 12px 16px; background-color: #ffffff; border-bottom: 1px solid #e9ecef; color: #333; font-size: 14px;">' . htmlspecialchars($environment['memory_peak'] ?? '') . '</td></tr>';
            $html .= '<tr><td style="padding: 12px 16px; background-color: #f8f9fa; color: #666; font-size: 14px;"><strong>Memory Limit:</strong></td>';
            $html .= '<td style="padding: 12px 16px; background-color: #ffffff; color: #333; font-size: 14px;">' . htmlspecialchars($environment['memory_limit'] ?? '') . '</td></tr>';
            $html .= '</table>';
        }

        // Note
        $html .= '<div style="background-color: #e3f2fd; padding: 16px 20px; border-radius: 4px; border-left: 4px solid #2196f3;">';
        $html .= '<p style="margin: 0; color: #1565c0; font-size: 13px; line-height: 1.6;"><strong>Note:</strong> This is an automated error notification. Identical errors are grouped together and will not trigger multiple notifications within the configured throttle period.</p>';
        $html .= '</div>';

        $html .= '</td></tr>';

        // Footer
        $html .= '<tr><td style="background-color: #f8f9fa; padding: 20px 30px; text-align: center; border-radius: 0 0 8px 8px; border-top: 1px solid #e9ecef;">';
        $html .= '<p style="margin: 0 0 8px 0; color: #666; font-size: 12px;">Automated alert from <strong>' . htmlspecialchars($store['name'] ?? '') . ' Error Monitor</strong></p>';
        $html .= '<p style="margin: 0; color: #999; font-size: 11px;">This is an automated message. Please do not reply to this email.</p>';
        $html .= '</td></tr>';

        $html .= '</table></td></tr></table>';
        $html .= '</body></html>';

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
        $store = $errorData['store'] ?? [];
        $request = $errorData['request'] ?? [];
        $storeName = $store['name'] ?? 'our website';
        $timestamp = $errorData['timestamp_formatted'] ?? date('F j, Y g:i:s A T');

        $html = '<!DOCTYPE html>';
        $html .= '<html>';
        $html .= '<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>';
        $html .= '<body style="margin: 0; padding: 0; background-color: #f4f4f4; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;">';
        $html .= '<table role="presentation" style="width: 100%; border-collapse: collapse; background-color: #f4f4f4; padding: 40px 0;">';
        $html .= '<tr><td align="center">';
        $html .= '<table role="presentation" style="width: 600px; border-collapse: collapse; background-color: #ffffff; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">';

        // Header
        $html .= '<tr><td style="background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%); padding: 40px 30px; text-align: center; border-radius: 8px 8px 0 0;">';
        $html .= '<div style="background-color: rgba(255,255,255,0.2); border-radius: 50%; width: 80px; height: 80px; margin: 0 auto 20px; display: inline-block; line-height: 80px;">';
        $html .= '<span style="font-size: 48px;">🔔</span></div>';
        $html .= '<h1 style="margin: 0; color: #ffffff; font-size: 28px; font-weight: 600;">Website Issue Detected</h1>';
        $html .= '<p style="margin: 10px 0 0 0; color: #ffffff; opacity: 0.9; font-size: 16px;">We\'re On It!</p>';
        $html .= '</td></tr>';

        // Body
        $html .= '<tr><td style="padding: 40px 30px;">';

        $html .= '<p style="color: #333; font-size: 16px; line-height: 1.6; margin: 0 0 30px 0;">This is an automated notification from ' . htmlspecialchars($storeName) . '.</p>';

        // Alert Message
        $html .= '<div style="background-color: #fff3cd; border-left: 4px solid #ff9800; padding: 16px 20px; margin-bottom: 30px; border-radius: 4px;">';
        $html .= '<p style="margin: 0; color: #856404; font-size: 16px; font-weight: 500;">⚠ An issue has been detected on the website and our technical team has been automatically notified.</p></div>';

        // Issue Summary Table
        $html .= '<table style="width: 100%; border-collapse: collapse; margin-bottom: 30px;">';
        $html .= '<tr><td style="padding: 12px 16px; background-color: #f8f9fa; border-bottom: 1px solid #e9ecef; color: #666; font-size: 14px; width: 35%;"><strong>Detected At:</strong></td>';
        $html .= '<td style="padding: 12px 16px; background-color: #ffffff; border-bottom: 1px solid #e9ecef; color: #333; font-size: 14px;">' . htmlspecialchars($timestamp) . '</td></tr>';
        $html .= '<tr><td style="padding: 12px 16px; background-color: #f8f9fa; border-bottom: 1px solid #e9ecef; color: #666; font-size: 14px;"><strong>Location:</strong></td>';
        $html .= '<td style="padding: 12px 16px; background-color: #ffffff; border-bottom: 1px solid #e9ecef; color: #333; font-size: 14px; word-break: break-word;">' . htmlspecialchars($request['url'] ?? '') . '</td></tr>';
        $html .= '<tr><td style="padding: 12px 16px; background-color: #f8f9fa; color: #666; font-size: 14px;"><strong>Store:</strong></td>';
        $html .= '<td style="padding: 12px 16px; background-color: #ffffff; color: #333; font-size: 14px;">' . htmlspecialchars($storeName) . '</td></tr>';
        $html .= '</table>';

        // Status
        $html .= '<div style="background-color: #f8f9fa; padding: 24px; border-radius: 6px; margin-bottom: 20px;">';
        $html .= '<h2 style="margin: 0 0 16px 0; color: #333; font-size: 18px; font-weight: 600;">What\'s Happening:</h2>';
        $html .= '<ul style="margin: 0; padding-left: 20px; color: #555; font-size: 14px; line-height: 1.8;">';
        $html .= '<li style="margin-bottom: 8px;">✅ Issue automatically detected and logged</li>';
        $html .= '<li style="margin-bottom: 8px;">✅ Development team notified immediately</li>';
        $html .= '<li style="margin-bottom: 8px;">✅ Investigation and resolution in progress</li>';
        $html .= '<li>✅ You will be updated on the resolution</li>';
        $html .= '</ul></div>';

        // Note
        $html .= '<div style="background-color: #e3f2fd; padding: 16px 20px; border-radius: 4px; border-left: 4px solid #2196f3;">';
        $html .= '<p style="margin: 0; color: #1565c0; font-size: 13px; line-height: 1.6;"><strong>Note:</strong> Our team is working to resolve this issue as quickly as possible. You do not need to take any action at this time.</p>';
        $html .= '</div>';

        $html .= '</td></tr>';

        // Footer
        $html .= '<tr><td style="background-color: #f8f9fa; padding: 20px 30px; text-align: center; border-radius: 0 0 8px 8px; border-top: 1px solid #e9ecef;">';
        $html .= '<p style="margin: 0 0 8px 0; color: #666; font-size: 12px;">Automated notification from <strong>' . htmlspecialchars($storeName) . '</strong></p>';
        $html .= '<p style="margin: 0; color: #999; font-size: 11px;">This is an automated message. Please do not reply to this email.</p>';
        $html .= '</td></tr>';

        $html .= '</table></td></tr></table>';
        $html .= '</body></html>';

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
