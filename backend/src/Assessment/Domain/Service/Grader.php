<?php

declare(strict_types=1);

namespace App\Assessment\Domain\Service;

use App\Assessment\Domain\Model\AnswerKey;
use App\Assessment\Domain\Model\GradeResult;

/**
 * Pure grading logic — no framework or persistence dependency. Kept separate
 * from Attempt/SubmitAttemptService so a future pass/fail threshold or
 * weighted scoring rule has one obvious place to live.
 */
final class Grader
{
    /**
     * @param list<string|null> $submittedAnswers indexed the same as the question order in $key
     */
    public function grade(AnswerKey $key, array $submittedAnswers): GradeResult
    {
        $total = $key->count();
        $score = 0;
        $perQuestion = [];

        for ($i = 0; $i < $total; ++$i) {
            $correct = $key->correctAnswerAt($i) ?? '';
            $submitted = $submittedAnswers[$i] ?? null;
            $isCorrect = null !== $submitted && $submitted === $correct;

            if ($isCorrect) {
                ++$score;
            }

            $perQuestion[] = [
                'correct' => $correct,
                'isCorrect' => $isCorrect,
                'explanation' => $key->explanationAt($i),
            ];
        }

        return new GradeResult($score, $total, $perQuestion);
    }
}
