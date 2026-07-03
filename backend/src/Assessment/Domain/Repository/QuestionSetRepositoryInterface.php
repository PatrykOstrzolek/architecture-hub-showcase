<?php

declare(strict_types=1);

namespace App\Assessment\Domain\Repository;

use App\Assessment\Domain\Model\QuestionSet;

interface QuestionSetRepositoryInterface
{
    public function find(int $id): ?QuestionSet;

    /**
     * Same as find(), but eager-loads the full items -> question -> options
     * chain in one query — use this wherever the questions/options are
     * actually going to be read (grading, headless resolution, the admin
     * detail view), to avoid an N+1 lazy-load cascade.
     */
    public function findWithQuestions(int $id): ?QuestionSet;

    /**
     * @param list<int> $ids
     *
     * @return list<QuestionSet>
     */
    public function findByIds(array $ids): array;

    /**
     * Bounded, ordered (id DESC) page of QuestionSets — used by the admin
     * list path when no `ids` filter is present. Replaces the previous
     * unbounded findAll() call on that path.
     *
     * @return list<QuestionSet>
     */
    public function findPaginated(int $page, int $limit): array;

    /**
     * Total QuestionSet count, unfiltered — used to compute PaginatedRepresentation's
     * total/pages alongside findPaginated().
     */
    public function count(): int;

    /**
     * Ids of every QuestionSet that references the given Question, via the
     * QuestionSetItem join entity — a Question can appear in more than one
     * QuestionSet, so this drives cache invalidation from QuestionController
     * (Requirement 5): editing/removing a Question must invalidate every
     * QuestionSet that includes it, not just one.
     *
     * @return list<int>
     */
    public function findQuestionSetIdsContaining(int $questionId): array;

    public function save(QuestionSet $questionSet): void;

    public function remove(QuestionSet $questionSet): void;
}
