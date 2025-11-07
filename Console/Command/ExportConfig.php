<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */
declare(strict_types=1);

namespace Hryvinskyi\ErrorReporting\Console\Command;

use Hryvinskyi\ErrorReporting\Api\ConfigStorageInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Console command to export error reporting configuration to filesystem
 */
class ExportConfig extends Command
{
    /**
     * @param ConfigStorageInterface $configStorage
     * @param string|null $name
     */
    public function __construct(
        private readonly ConfigStorageInterface $configStorage,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setName('hryvinskyi:error-reporting:export-config');
        $this->setDescription('Export error reporting configuration to filesystem for failover support');
        $this->setHelp(
            <<<HELP
This command exports the current error reporting configuration from the database to the filesystem.

The exported configuration will be used as a fallback if the database becomes unavailable,
ensuring that error notifications continue to work even during database outages.

Configuration is automatically exported when you save settings in the admin panel,
but you can use this command to manually export at any time.

Usage:
  php bin/magento hryvinskyi:error-reporting:export-config

The configuration will be saved to:
  var/error_reporting_config.json
HELP
        );

        parent::configure();
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Exporting error reporting configuration...</info>');

        try {
            $result = $this->configStorage->exportConfig();

            if ($result) {
                $output->writeln('<info>✓ Configuration exported successfully to var/error_reporting_config.json</info>');
                $output->writeln('<comment>Error reporting will now use this configuration if database becomes unavailable.</comment>');
                return Command::SUCCESS;
            } else {
                $output->writeln('<error>✗ Failed to export configuration</error>');
                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $output->writeln(sprintf('<error>✗ Error: %s</error>', $e->getMessage()));
            return Command::FAILURE;
        }
    }
}
