<?php

declare(strict_types=1);

namespace App\Command;

use App\Contract\LimitApplierInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:sync-limits',
    description: 'Push current rate limits for all active providers to Stalwart',
)]
final class SyncLimitsCommand extends Command
{
    public function __construct(
        private readonly LimitApplierInterface $limitApplier,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->limitApplier->syncAll();

        $output->writeln('Rate limits synced to Stalwart.');

        return Command::SUCCESS;
    }
}
