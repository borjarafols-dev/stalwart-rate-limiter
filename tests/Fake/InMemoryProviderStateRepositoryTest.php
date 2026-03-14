<?php

declare(strict_types=1);

namespace App\Tests\Fake;

use App\Entity\ProviderState;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InMemoryProviderStateRepositoryTest extends TestCase
{
    #[Test]
    public function findByProviderReturnsNullWhenEmpty(): void
    {
        $repo = new InMemoryProviderStateRepository();

        self::assertNull($repo->findByProvider('gmail'));
    }

    #[Test]
    public function saveAndFindByProvider(): void
    {
        $repo = new InMemoryProviderStateRepository();
        $state = new ProviderState('gmail');

        $repo->save($state);

        self::assertSame($state, $repo->findByProvider('gmail'));
    }

    #[Test]
    public function findAllActiveReturnsRecentProviders(): void
    {
        $repo = new InMemoryProviderStateRepository();

        $active = new ProviderState('gmail');
        $repo->save($active);

        $stale = new ProviderState('yahoo');
        $stale->setLastEvent(new \DateTimeImmutable('-60 days'));
        $repo->save($stale);

        $result = $repo->findAllActive();

        self::assertCount(1, $result);
        self::assertSame('gmail', $result[0]->getProvider());
    }

    #[Test]
    public function findStaleReturnsOldProviders(): void
    {
        $repo = new InMemoryProviderStateRepository();

        $active = new ProviderState('gmail');
        $repo->save($active);

        $stale = new ProviderState('yahoo');
        $stale->setLastEvent(new \DateTimeImmutable('-10 days'));
        $repo->save($stale);

        $result = $repo->findStale(new \DateTimeImmutable('-7 days'));

        self::assertCount(1, $result);
        self::assertSame('yahoo', $result[0]->getProvider());
    }

    #[Test]
    public function getAllReturnsAllStates(): void
    {
        $repo = new InMemoryProviderStateRepository();
        $repo->save(new ProviderState('gmail'));
        $repo->save(new ProviderState('yahoo'));

        self::assertCount(2, $repo->getAll());
    }

    #[Test]
    public function resetClearsAllStates(): void
    {
        $repo = new InMemoryProviderStateRepository();
        $repo->save(new ProviderState('gmail'));

        $repo->reset();

        self::assertNull($repo->findByProvider('gmail'));
        self::assertCount(0, $repo->getAll());
    }

    #[Test]
    public function saveOverwritesExistingProvider(): void
    {
        $repo = new InMemoryProviderStateRepository();

        $state = new ProviderState('gmail');
        $repo->save($state);

        $state->applyLevel(4);
        $repo->save($state);

        $found = $repo->findByProvider('gmail');
        self::assertNotNull($found);
        self::assertSame(4, $found->getCurrentLevel());
    }
}
