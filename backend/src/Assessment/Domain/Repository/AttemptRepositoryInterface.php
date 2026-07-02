<?php

declare(strict_types=1);

namespace App\Assessment\Domain\Repository;

use App\Assessment\Domain\Model\Attempt;

interface AttemptRepositoryInterface
{
    public function find(int $id): ?Attempt;

    /**
     * @return list<Attempt>
     */
    public function findAll(): array;

    public function save(Attempt $attempt): void;

    public function remove(Attempt $attempt): void;
}
