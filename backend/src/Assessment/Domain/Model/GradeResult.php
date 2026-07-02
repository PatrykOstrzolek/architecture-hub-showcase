<?php

declare(strict_types=1);

namespace App\Assessment\Domain\Model;

final class GradeResult
{
    /**
     * @param list<array{correctOptionIds: list<int>, submittedOptionIds: list<int>, isCorrect: bool, explanation: string|null}> $perQuestion
     */
    public function __construct(
        public readonly int $score,
        public readonly int $total,
        public readonly array $perQuestion,
    ) {
    }
}
