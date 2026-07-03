<?php

declare(strict_types=1);

namespace App\Assessment\Domain\Repository;

use App\Assessment\Domain\Model\Question;

interface QuestionRepositoryInterface
{
    public function find(int $id): ?Question;

    /**
     * Loads a Question with its Options eagerly fetched in a single query
     * (JOIN FETCH), avoiding the N+1 lazy-load that plain find() triggers
     * when the caller accesses getOptions() — used by the detail endpoint.
     */
    public function findWithOptions(int $id): ?Question;

    /**
     * @param list<int> $ids
     *
     * @return list<Question>
     */
    public function findByIds(array $ids): array;

    /**
     * Bounded, ordered (id DESC) page of Questions — used by the admin list
     * path when no `ids` filter is present. Replaces the previous unbounded
     * findAll() call on that path.
     *
     * @return list<Question>
     */
    public function findPaginated(int $page, int $limit): array;

    /**
     * Total Question count, unfiltered — used to compute PaginatedRepresentation's
     * total/pages alongside findPaginated().
     */
    public function count(): int;

    public function save(Question $question): void;

    public function remove(Question $question): void;
}
