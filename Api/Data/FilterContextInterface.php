<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */
declare(strict_types=1);

namespace Hryvinskyi\ErrorReporting\Api\Data;

use Magento\Framework\App\Request\Http as RequestHttp;

/**
 * Data object containing context information for error filtering
 *
 * This object encapsulates all information needed to make filtering decisions,
 * making it easy to extend with additional context without changing method signatures.
 *
 * Extends DataObject, so getData() and hasData() are available for additional context.
 * Example: $context->setData('area', 'adminhtml'); $context->getData('area');
 */
interface FilterContextInterface
{
    /**
     * Get the exception that occurred
     *
     * @return \Throwable
     */
    public function getException(): \Throwable;

    /**
     * Set the exception
     *
     * @param \Throwable $exception
     * @return $this
     */
    public function setException(\Throwable $exception): self;

    /**
     * Get the HTTP request context
     *
     * @return RequestHttp
     */
    public function getRequest(): RequestHttp;

    /**
     * Set the HTTP request
     *
     * @param RequestHttp $request
     * @return $this
     */
    public function setRequest(RequestHttp $request): self;

    /**
     * Get the severity level
     *
     * @return string One of: 'warning', 'error', 'critical'
     */
    public function getSeverity(): string;

    /**
     * Set the severity level
     *
     * @param string $severity
     * @return $this
     */
    public function setSeverity(string $severity): self;
}