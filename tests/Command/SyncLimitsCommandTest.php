<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\SyncLimitsCommand;
use App\Contract\LimitApplierInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class SyncLimitsCommandTest extends TestCase
{
    #[Test]
    public function executeCallsSyncAllAndReturnsSuccess(): void
    {
        $limitApplier = $this->createMock(LimitApplierInterface::class);
        $limitApplier->expects(self::once())->method('syncAll');

        $command = new SyncLimitsCommand($limitApplier);

        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($application->find('app:sync-limits'));
        $exitCode = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Rate limits synced', $tester->getDisplay());
    }
}
