<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */
declare(strict_types=1);

namespace Hryvinskyi\ErrorReporting\Api;

/**
 * Interface for configuration storage
 */
interface ConfigStorageInterface
{
    /**
     * Export configuration to filesystem from database
     *
     * @return bool
     */
    public function exportConfig(): bool;

    /**
     * Save configuration directly to filesystem (bypassing database)
     *
     * @param array<string, mixed> $config
     * @return bool
     */
    public function saveConfig(array $config): bool;

    /**
     * Get configuration from storage
     *
     * @return array<string, mixed>|null
     */
    public function getConfig(): ?array;

    /**
     * Check if configuration exists in storage
     *
     * @return bool
     */
    public function hasConfig(): bool;
}
