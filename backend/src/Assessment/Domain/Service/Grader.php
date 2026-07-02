<?php

declare(strict_types=1);

namespace App\Assessment\Domain\Service;

use App\Assessment\Domain\Model\GradeResult;
use App\Assessment\Domain\Model\Option;
use App\Assessment\Domain\Model\QuestionSet;

/**
 * Pure grading logic — no framework or persistence dependency. Compares
 * Option *object references* (identity), not database ids: a Question can
 * have zero, one, or many correct Options, so correctness for each question
 * is "the submitted set of Options equals the set of Options marked
 * isCorrect" — a single-answer question is just the count()===1 case of the
 * same rule, no special-casing needed for "select all that apply".
 */
final class Grader
{
    /**
     * @param list<list<Option>> $submittedOptionSets one set of selected Options per question,
     *                                                indexed the same as QuestionSet::getOrderedQuestions()
     */
    public function grade(QuestionSet $questionSet, array $submittedOptionSets): GradeResult
    {
        $questions = $questionSet->getOrderedQuestions();
        $total = \count($questions);
        $score = 0;
        $perQuestion = [];

        for ($i = 0; $i < $total; ++$i) {
            $question = $questions[$i];
            $correctOptions = \array_values(\array_filter(
                $question->getOptions(),
                static fn (Option $option): bool => $option->isCorrect(),
            ));
            $submitted = $submittedOptionSets[$i] ?? [];

            $isCorrect = $this->sameOptions($correctOptions, $submitted);

            if ($isCorrect) {
                ++$score;
            }

            $perQuestion[] = [
                'correctOptionIds' => $this->ids($correctOptions),
                'submittedOptionIds' => $this->ids($submitted),
                'isCorrect' => $isCorrect,
                'explanation' => $question->getExplanation(),
            ];
        }

        return new GradeResult($score, $total, $perQuestion);
    }

    /**
     * @param list<Option> $a
     * @param list<Option> $b
     */
    private function sameOptions(array $a, array $b): bool
    {
        if (\count($a) !== \count($b)) {
            return false;
        }

        foreach ($a as $option) {
            if (!\in_array($option, $b, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<Option> $options
     *
     * @return list<int>
     */
    private function ids(array $options): array
    {
        return \array_values(\array_filter(\array_map(
            static fn (Option $option): ?int => $option->getId(),
            $options,
        ), static fn (?int $id): bool => null !== $id));
    }
}
