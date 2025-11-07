<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */
declare(strict_types=1);

namespace Hryvinskyi\ErrorReporting\Model\Data;

use Hryvinskyi\ErrorReporting\Api\Data\FilterContextInterface;
use Magento\Framework\App\Request\Http as RequestHttp;
use Magento\Framework\DataObject;

/**
 * Data object containing context information for error filtering
 *
 * Extends Magento's DataObject for flexibility and follows Magento conventions.
 * Should be created via FilterContextFactory.
 */
class FilterContext extends DataObject implements FilterContextInterface
{
    /**
     * Data keys
     */
    private const KEY_EXCEPTION = 'exception';
    private const KEY_REQUEST = 'request';
    private const KEY_SEVERITY = 'severity';

    /**
     * {@inheritDoc}
     */
    public function getException(): \Throwable
    {
        return $this->_getData(self::KEY_EXCEPTION);
    }

    /**
     * Set exception
     *
     * @param \Throwable $exception
     * @return $this
     */
    public function setException(\Throwable $exception): self
    {
        return $this->setData(self::KEY_EXCEPTION, $exception);
    }

    /**
     * {@inheritDoc}
     */
    public function getRequest(): RequestHttp
    {
        return $this->_getData(self::KEY_REQUEST);
    }

    /**
     * Set request
     *
     * @param RequestHttp $request
     * @return $this
     */
    public function setRequest(RequestHttp $request): self
    {
        return $this->setData(self::KEY_REQUEST, $request);
    }

    /**
     * {@inheritDoc}
     */
    public function getSeverity(): string
    {
        return $this->_getData(self::KEY_SEVERITY) ?? 'error';
    }

    /**
     * Set severity level
     *
     * @param string $severity
     * @return $this
     */
    public function setSeverity(string $severity): self
    {
        return $this->setData(self::KEY_SEVERITY, $severity);
    }
}