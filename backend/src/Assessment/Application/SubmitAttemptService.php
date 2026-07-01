<?php

declare(strict_types=1);

namespace App\Assessment\Application;

use App\Assessment\Domain\Exception\ExerciseNotFoundException;
use App\Assessment\Domain\Model\Attempt;
use App\Assessment\Domain\Model\GradeResult;
use App\Assessment\Domain\Service\Grader;
use App\Assessment\Infrastructure\AttemptRepository;
use App\Assessment\Infrastructure\ExerciseContentReader;

readonly class SubmitAttemptService
{
    public function __construct(
        private ExerciseContentReader $contentReader,
        private Grader $grader,
        private AttemptRepository $attempts,
    ) {
    }

    /**
     * @param list<string|null> $answers
     */
    public function submit(string $exerciseUuid, string $sessionId, array $answers): GradeResult
    {
        $answerKey = $this->contentReader->findAnswerKey($exerciseUuid);
        if (null === $answerKey) {
            throw new ExerciseNotFoundException($exerciseUuid);
        }

        $result = $this->grader->grade($answerKey, $answers);

        $this->attempts->save(new Attempt($exerciseUuid, $sessionId, $answers, $result->score, $result->total));

        return $result;
    }
}
