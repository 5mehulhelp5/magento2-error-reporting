<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */
declare(strict_types=1);

namespace Hryvinskyi\ErrorReporting\Model;

use Hryvinskyi\ErrorReporting\Api\Data\FilterContextInterface;
use Hryvinskyi\ErrorReporting\Api\Data\FilterContextInterfaceFactory;
use Hryvinskyi\ErrorReporting\Api\ErrorFilterInterface;
use Hryvinskyi\ErrorReporting\Api\Filter\FilterInterface;
use Hryvinskyi\ErrorReporting\Model\Filter\ControllerFilter;
use Hryvinskyi\ErrorReporting\Model\Filter\ExceptionFilter;
use Magento\Framework\App\Request\Http as RequestHttp;

/**
 * Composite service for filtering errors based on multiple filter strategies
 *
 * Uses the composite pattern to apply multiple filters configured via di.xml.
 */
class ErrorFilter implements ErrorFilterInterface
{
    /**
     * @param FilterContextInterfaceFactory $filterContextFactory
     * @param array<FilterInterface> $filters Array of filter implementations
     */
    public function __construct(
        private readonly FilterContextInterfaceFactory $filterContextFactory,
        private readonly array $filters = []
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function shouldReport(\Throwable $exception, RequestHttp $request, string $severity): bool
    {
        $context = $this->filterContextFactory->create([
            'data' => [
                'exception' => $exception,
                'request' => $request,
                'severity' => $severity,
            ]
        ]);

        // Apply all filters - if any filter returns true (should filter), don't report
        foreach ($this->filters as $filter) {
            if (!$filter instanceof FilterInterface) {
                continue;
            }

            if ($filter->shouldFilter($context)) {
                return false;
            }
        }

        return true;
    }
}
