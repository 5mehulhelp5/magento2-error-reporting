<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */
declare(strict_types=1);

namespace Hryvinskyi\ErrorReporting\Model\Collector;

use Hryvinskyi\ErrorReporting\Api\Collector\SeverityResolverInterface;

/**
 * Service for determining error severity levels
 *
 * Uses instanceof checks for proper class hierarchy support.
 * More specific exception types are checked before general ones.
 */
class SeverityResolver implements SeverityResolverInterface
{
    /**
     * Severity level constants
     */
    public const SEVERITY_CRITICAL = 'critical';
    public const SEVERITY_ERROR = 'error';
    public const SEVERITY_WARNING = 'warning';

    /**
     * @param array<string, array<int, string>> $severityPatterns Configurable severity class patterns
     */
    public function __construct(
        private readonly array $severityPatterns = []
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function resolve(\Throwable $exception): string
    {
        // Get class patterns from configuration or use defaults
        $patterns = $this->getSeverityPatterns();

        // Check each severity level in priority order: critical, error, warning
        foreach ([self::SEVERITY_CRITICAL, self::SEVERITY_ERROR, self::SEVERITY_WARNING] as $severity) {
            if (!isset($patterns[$severity])) {
                continue;
            }

            foreach ($patterns[$severity] as $exceptionClass) {
                // Skip if class doesn't exist (Magento version compatibility)
                if (!class_exists($exceptionClass) && !interface_exists($exceptionClass)) {
                    continue;
                }

                // Use instanceof for proper class hierarchy checking
                if ($exception instanceof $exceptionClass) {
                    return $severity;
                }
            }
        }

        // Default to error level
        return self::SEVERITY_ERROR;
    }

    /**
     * Get severity patterns with defaults
     *
     * Organizes exceptions by severity:
     * - CRITICAL: System failures, database errors, PHP errors that prevent execution
     * - ERROR: Application errors that break functionality but allow partial operation
     * - WARNING: Expected exceptions that allow site to render (404, validation, etc.)
     *
     * @return array<string, array<int, string>>
     */
    private function getSeverityPatterns(): array
    {
        $defaults = [
            // CRITICAL: System-level failures that prevent normal operation
            self::SEVERITY_CRITICAL => [
                // Database critical errors
                \Magento\Framework\DB\Adapter\TableNotFoundException::class,
                \Magento\Framework\DB\Adapter\DeadlockException::class,
                \Magento\Framework\DB\Adapter\LockWaitException::class,
                \Magento\Framework\DB\Adapter\DuplicateException::class,
                \Magento\Framework\DB\Adapter\ConnectionException::class,
                \PDOException::class,
                \Zend_Db_Adapter_Exception::class,
                \Zend_Db_Statement_Exception::class,

                // PHP Fatal Errors
                \Error::class, // Base Error class (PHP 7+)
                \ParseError::class,
                \TypeError::class,
                \CompileError::class,
                \ArithmeticError::class,

                // Magento critical errors
                \Magento\Framework\Exception\FileSystemException::class,
                \Magento\Framework\Exception\SessionException::class,
            ],

            // ERROR: Application errors that break functionality
            self::SEVERITY_ERROR => [
                // Magento application errors
                \Magento\Framework\Exception\StateException::class,
                \Magento\Framework\Exception\InputException::class,
                \Magento\Framework\Exception\AuthorizationException::class,
                \Magento\Framework\Exception\AuthenticationException::class,
                \Magento\Framework\Exception\MailException::class,
                \Magento\Framework\Exception\PaymentException::class,
                \Magento\Framework\Exception\BulkException::class,
                \Magento\Framework\Exception\SecurityViolationException::class,
                \Magento\Framework\Exception\CouldNotSaveException::class,
                \Magento\Framework\Exception\CouldNotDeleteException::class,

                // Expected runtime exceptions
                \Magento\Framework\Exception\AlreadyExistsException::class,
                \Magento\Framework\Exception\TemporaryStateExceptionInterface::class,

                // SPL exceptions
                \RuntimeException::class,
                \LogicException::class,
                \InvalidArgumentException::class,
                \OutOfBoundsException::class,
                \OutOfRangeException::class,
                \UnexpectedValueException::class,
                \BadFunctionCallException::class,
                \BadMethodCallException::class,

                // Zend exceptions
                \Zend_Exception::class,

                // Generic exception (catch-all at the end)
                \Exception::class,
            ],

            // WARNING: Expected exceptions that allow site to continue rendering
            self::SEVERITY_WARNING => [
                // Magento "not found" exceptions - these are expected during normal operation
                \Magento\Framework\Exception\NoSuchEntityException::class,
                \Magento\Framework\Exception\NotFoundException::class,

                // Validation and user input errors - expected in normal operation
                \Magento\Framework\Exception\LocalizedException::class,
                \Magento\Framework\Exception\ValidatorException::class,
                \Magento\Framework\Validator\Exception::class,
            ],
        ];

        // Merge custom patterns if provided via di.xml
        if (!empty($this->severityPatterns)) {
            foreach ($this->severityPatterns as $severity => $patterns) {
                if (isset($defaults[$severity])) {
                    // Prepend custom patterns so they take priority
                    $defaults[$severity] = array_merge($patterns, $defaults[$severity]);
                } else {
                    $defaults[$severity] = $patterns;
                }
            }
        }

        return $defaults;
    }
}