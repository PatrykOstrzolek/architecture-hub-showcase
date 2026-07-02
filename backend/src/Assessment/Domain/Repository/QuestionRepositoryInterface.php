<?php

declare(strict_types=1);

namespace App\Assessment\Domain\Repository;

use App\Assessment\Domain\Model\Question;

interface QuestionRepositoryInterface
{
    public function find(int $id): ?Question;

    /**
     * @return list<Question>
     */
    public function findAll(): array;

    /**
     * @param list<int> $ids
     *
     * @return list<Question>
     */
    public function findByIds(array $ids): array;

    public function save(Question $question): void;

    public function remove(Question $question): void;
}
