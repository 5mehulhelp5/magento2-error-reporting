<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */
declare(strict_types=1);

namespace Hryvinskyi\ErrorReporting\Model\Filter;

use Hryvinskyi\ErrorReporting\Api\ConfigInterface;
use Hryvinskyi\ErrorReporting\Api\Data\FilterContextInterface;
use Hryvinskyi\ErrorReporting\Api\Filter\FilterInterface;
use Hryvinskyi\ErrorReporting\Api\Filter\PatternMatcherInterface;

/**
 * Filter exceptions based on blacklist patterns
 *
 * Matches exception class, message, and file path against configured patterns.
 */
class ExceptionFilter implements FilterInterface
{
    /**
     * @param ConfigInterface $config
     * @param PatternMatcherInterface $patternMatcher
     */
    public function __construct(
        private readonly ConfigInterface $config,
        private readonly PatternMatcherInterface $patternMatcher
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function shouldFilter(FilterContextInterface $context): bool
    {
        $blacklist = $this->config->getErrorBlacklist();
        if (empty($blacklist)) {
            return false;
        }

        $patterns = $this->patternMatcher->parsePatterns($blacklist);
        if (empty($patterns)) {
            return false;
        }

        $exception = $context->getException();
        $values = [
            get_class($exception),
            $exception->getMessage(),
            $exception->getFile(),
        ];

        return $this->patternMatcher->matchesAny($patterns, $values);
    }
}