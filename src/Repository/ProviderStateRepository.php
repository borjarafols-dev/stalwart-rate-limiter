<?php

declare(strict_types=1);

namespace App\Repository;

use App\Contract\ProviderStateRepositoryInterface;
use App\Entity\ProviderState;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProviderState>
 */
final class ProviderStateRepository extends ServiceEntityRepository implements ProviderStateRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProviderState::class);
    }

    public function findByProvider(string $provider): ?ProviderState
    {
        return $this->find($provider);
    }

    /**
     * @return list<ProviderState>
     */
    public function findAllActive(): array
    {
        $cutoff = new \DateTimeImmutable('-30 days');

        /* @var list<ProviderState> */
        return $this->createQueryBuilder('p')
            ->where('p.lastEvent >= :cutoff')
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<ProviderState>
     */
    public function findStale(\DateTimeImmutable $cutoff): array
    {
        /* @var list<ProviderState> */
        return $this->createQueryBuilder('p')
            ->where('p.lastEvent < :cutoff')
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->getResult();
    }

    public function save(ProviderState $state): void
    {
        $this->getEntityManager()->persist($state);
        $this->getEntityManager()->flush();
    }
}
