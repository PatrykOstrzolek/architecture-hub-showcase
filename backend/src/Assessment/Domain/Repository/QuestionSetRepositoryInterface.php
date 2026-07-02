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
     * @return list<QuestionSet>
     */
    public function findAll(): array;

    /**
     * @param list<int> $ids
     *
     * @return list<QuestionSet>
     */
    public function findByIds(array $ids): array;

    public function save(QuestionSet $questionSet): void;

    public function remove(QuestionSet $questionSet): void;
}
