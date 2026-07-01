<?php

declare(strict_types=1);

namespace App\Assessment\Infrastructure;

use App\Assessment\Domain\Model\Attempt;
use Doctrine\ORM\EntityManagerInterface;

final readonly class AttemptRepository
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
