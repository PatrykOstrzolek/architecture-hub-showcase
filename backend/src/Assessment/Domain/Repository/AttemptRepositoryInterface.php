<?php

declare(strict_types=1);

namespace App\Assessment\Domain\Repository;

use App\Assessment\Domain\Model\Attempt;

interface AttemptRepositoryInterface
{
    public function find(int $id): ?Attempt;

    /**
     * Bounded, ordered (createdAt DESC) page of Attempts — backed by the
     * idx_exercise_attempt_created_at index. Replaces the previous
     * unbounded findAll() call on the admin list path.
     *
     * @return list<Attempt>
     */
    public function findPaginated(int $page, int $limit): array;

    /**
     * Total Attempt count, unfiltered — used to compute PaginatedRepresentation's
     * total/pages alongside findPaginated().
     */
    public function count(): int;

    public function save(Attempt $attempt): void;

    public function remove(Attempt $attempt): void;
}
