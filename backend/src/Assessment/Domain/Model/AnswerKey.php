<?php

declare(strict_types=1);

namespace App\Assessment\Domain\Model;

final class AnswerKey
{
    /**
     * @param list<array{correct: string, explanation: string|null}> $questions
     */
    public function __construct(private readonly array $questions)
    {
    }

    public function count(): int
    {
        return \count($this->questions);
    }

    public function correctAnswerAt(int $index): ?string
    {
        return $this->questions[$index]['correct'] ?? null;
    }

    public function explanationAt(int $index): ?string
    {
        return $this->questions[$index]['explanation'] ?? null;
    }
}
