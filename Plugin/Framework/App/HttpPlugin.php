<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */
declare(strict_types=1);

namespace Hryvinskyi\ErrorReporting\Plugin\Framework\App;

use Closure;
use Exception;
use Hryvinskyi\ErrorReporting\Exception\ThrowableWrapperException;
use Magento\Framework\App\Http;
use Throwable;

/**
 * Plugin for Magento\Framework\App\Http
 *
 * Catches all errors including Throwables that aren't Exceptions
 * Similar to Yireo_Whoops but for error reporting instead of display
 */
class HttpPlugin
{
    /**
     * Wrap launch method to catch all Throwables
     *
     * @param Http $subject
     * @param Closure $proceed
     * @return mixed
     * @throws Exception
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundLaunch(Http $subject, Closure $proceed)
    {
        try {
            return $proceed();
        } catch (Throwable $e) {
            // Re-throw as Exception for Magento to handle
            if (!$e instanceof Exception) {
                /**
                 * Convert the Throwable to an exception for it to be caught by the main catch(\Exception) block.
                 *
                 * @see \Magento\Framework\App\Bootstrap::run
                 */
                throw new ThrowableWrapperException($e);
            }

            throw $e;
        }
    }
}