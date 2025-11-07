<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */
declare(strict_types=1);

namespace Hryvinskyi\ErrorReporting\Exception;

use Exception;
use Throwable;

/**
 * Exception wrapper that converts Throwables to Exceptions
 *
 * This class extends \Exception and provides the exact same output
 * as the original exception while allowing Throwable to be caught
 * by Exception handlers in Magento's Bootstrap::run()
 */
class ThrowableWrapperException extends Exception
{
    /**
     * Constructor
     *
     * Wraps a Throwable by copying all its properties to make it an Exception
     * while preserving the original file, line, trace, and message
     *
     * @param Throwable $throwable The original throwable to wrap
     */
    public function __construct(Throwable $throwable)
    {
        parent::__construct(
            $throwable->getMessage(),
            is_int($throwable->getCode()) ? $throwable->getCode() : 0
        );

        // Use reflection to override the final properties with original throwable's values
        $this->overrideProperty('file', $throwable->getFile());
        $this->overrideProperty('line', $throwable->getLine());
        $this->overrideProperty('trace', $throwable->getTrace());
    }

    /**
     * Override protected property using reflection
     *
     * @param string $property Property name
     * @param mixed $value Property value
     * @return void
     */
    private function overrideProperty(string $property, mixed $value): void
    {
        $reflection = new \ReflectionClass(Exception::class);
        $reflectionProperty = $reflection->getProperty($property);
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($this, $value);
    }
}