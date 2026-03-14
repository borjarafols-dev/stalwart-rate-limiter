<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\ProviderState;
use App\Repository\ProviderStateRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ProviderStateRepositoryTest extends KernelTestCase
{
    private ProviderStateRepository $repository;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $this->entityManager = $em;

        /** @var ProviderStateRepository $repo */
        $repo = $em->getRepository(ProviderState::class);
        $this->repository = $repo;

        $this->entityManager->createQuery('DELETE FROM App\Entity\ProviderState')->execute();
    }

    #[Test]
    public function findByProviderReturnsNullForUnknownProvider(): void
    {
        self::assertNull($this->repository->findByProvider('nonexistent'));
    }

    #[Test]
    public function saveAndFindByProvider(): void
    {
        $state = new ProviderState('gmail');
        $this->repository->save($state);

        $this->entityManager->clear();

        $found = $this->repository->findByProvider('gmail');
        self::assertNotNull($found);
        self::assertSame('gmail', $found->getProvider());
        self::assertSame(2, $found->getCurrentLevel());
    }

    #[Test]
    public function findAllActiveReturnsRecentProviders(): void
    {
        $active = new ProviderState('gmail');
        $this->repository->save($active);

        $stale = new ProviderState('yahoo');
        $stale->setLastEvent(new \DateTimeImmutable('-60 days'));
        $this->repository->save($stale);

        $this->entityManager->clear();

        $activeProviders = $this->repository->findAllActive();

        self::assertCount(1, $activeProviders);
        self::assertSame('gmail', $activeProviders[0]->getProvider());
    }

    #[Test]
    public function findStaleReturnsOldProviders(): void
    {
        $active = new ProviderState('gmail');
        $this->repository->save($active);

        $stale = new ProviderState('yahoo');
        $stale->setLastEvent(new \DateTimeImmutable('-10 days'));
        $this->repository->save($stale);

        $this->entityManager->clear();

        $cutoff = new \DateTimeImmutable('-7 days');
        $staleProviders = $this->repository->findStale($cutoff);

        self::assertCount(1, $staleProviders);
        self::assertSame('yahoo', $staleProviders[0]->getProvider());
    }

    #[Test]
    public function saveUpdatesExistingProvider(): void
    {
        $state = new ProviderState('gmail');
        $this->repository->save($state);

        $state->applyLevel(4);
        $this->repository->save($state);

        $this->entityManager->clear();

        $found = $this->repository->findByProvider('gmail');
        self::assertNotNull($found);
        self::assertSame(4, $found->getCurrentLevel());
    }
}
