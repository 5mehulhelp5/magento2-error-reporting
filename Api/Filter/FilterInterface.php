<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */
declare(strict_types=1);

namespace Hryvinskyi\ErrorReporting\Api\Filter;

use Hryvinskyi\ErrorReporting\Api\Data\FilterContextInterface;

/**
 * Base interface for error filtering strategies
 *
 * Implementations should follow Single Responsibility Principle,
 * each filter handling one specific filtering concern.
 */
interface FilterInterface
{
    /**
     * Determine if the error should be filtered out (not reported)
     *
     * @param FilterContextInterface $context The filter context containing exception, request, and severity
     * @return bool True if error should be filtered out (not reported), false otherwise
     */
    public function shouldFilter(FilterContextInterface $context): bool;
}