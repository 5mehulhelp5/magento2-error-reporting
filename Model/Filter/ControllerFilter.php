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
 * Filter errors based on controller/route configuration
 *
 * Supports both whitelist (include-only) and blacklist (exclude) patterns.
 * When whitelist is configured, only matching controllers are allowed.
 * Blacklist patterns exclude specific controllers from reporting.
 */
class ControllerFilter implements FilterInterface
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
        $request = $context->getRequest();
        $targets = $this->getTargetValues($request);

        $includePatterns = $this->patternMatcher->parsePatterns(
            $this->config->getIncludeOnlyControllers()
        );
        $excludePatterns = $this->patternMatcher->parsePatterns(
            $this->config->getExcludeControllers()
        );

        // If whitelist exists, check if controller matches
        if (!empty($includePatterns)) {
            if (!$this->patternMatcher->matchesAny($includePatterns, $targets)) {
                // Not in whitelist -> filter out
                return true;
            }
        }

        // Check blacklist - if any pattern matches, filter out
        if (!empty($excludePatterns)) {
            if ($this->patternMatcher->matchesAny($excludePatterns, $targets)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get array of target values to match against patterns
     *
     * @param \Magento\Framework\App\Request\Http $request
     * @return array<int, string> Array of controller identifiers and URL
     */
    public function getTargetValues(\Magento\Framework\App\Request\Http $request): array
    {
        return array_filter([
            trim($request->getFullActionName('/'), '/'),
            trim($request->getFullActionName('_'), '_'),
            $request->getRequestUri(),
        ]);
    }
}