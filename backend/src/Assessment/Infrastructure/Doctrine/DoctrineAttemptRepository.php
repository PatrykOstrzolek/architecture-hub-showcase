<?php

declare(strict_types=1);

namespace App\Assessment\Infrastructure\Doctrine;

use App\Assessment\Domain\Model\Attempt;
use App\Assessment\Domain\Repository\AttemptRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineAttemptRepository implements AttemptRepositoryInterface
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function find(int $id): ?Attempt
    {
        return $this->em->find(Attempt::class, $id);
    }

    /**
     * @return list<Attempt>
     */
    public function findPaginated(int $page, int $limit): array
    {
        /** @var list<Attempt> $result */
        $result = $this->em->createQueryBuilder()
            ->select('a')
            ->from(Attempt::class, 'a')
            ->orderBy('a.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $result;
    }

    public function count(): int
    {
        /** @var int|string $total */
        $total = $this->em->createQueryBuilder()
            ->select('COUNT(a.id)')
            ->from(Attempt::class, 'a')
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $total;
    }

    public function save(Attempt $attempt): void
    {
        $this->em->persist($attempt);
        $this->em->flush();
    }

    public function remove(Attempt $attempt): void
    {
        $this->em->remove($attempt);
        $this->em->flush();
    }
}
