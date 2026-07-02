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

    public function save(Attempt $attempt): void
    {
        $this->em->persist($attempt);
        $this->em->flush();
    }
}
