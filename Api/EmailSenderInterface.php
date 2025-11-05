<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */
declare(strict_types=1);

namespace Hryvinskyi\ErrorReporting\Api;

/**
 * Interface for sending error report emails
 */
interface EmailSenderInterface
{
    /**
     * Send error notification emails
     *
     * @param array<string, mixed> $errorData
     * @return bool
     */
    public function sendErrorNotification(array $errorData): bool;
}
