<?php

declare(strict_types=1);

namespace App\Assessment\Application;

use App\Assessment\Application\Port\ExerciseQuestionSetLocatorInterface;
use App\Assessment\Domain\Exception\ExerciseNotFoundException;
use App\Assessment\Domain\Model\Attempt;
use App\Assessment\Domain\Model\GradeResult;
use App\Assessment\Domain\Model\Option;
use App\Assessment\Domain\Repository\AttemptRepositoryInterface;
use App\Assessment\Domain\Repository\QuestionSetRepositoryInterface;
use App\Assessment\Domain\Service\Grader;

readonly class SubmitAttemptService
{
    public function __construct(
        private ExerciseQuestionSetLocatorInterface $locator,
        private QuestionSetRepositoryInterface $questionSets,
        private Grader $grader,
        private AttemptRepositoryInterface $attempts,
    ) {
    }

    /**
     * @param list<list<int>> $answers one set of selected Option ids per question, resolved
     *                                 against the loaded QuestionSet's real Options below —
     *                                 unresolvable ids are silently dropped (tolerant, matches
     *                                 this codebase's existing "never throws on bad input" style)
     */
    public function submit(string $exerciseUuid, string $sessionId, array $answers): GradeResult
    {
        $questionSetId = $this->locator->findQuestionSetId($exerciseUuid);
        if (null === $questionSetId) {
            throw new ExerciseNotFoundException($exerciseUuid);
        }

        $questionSet = $this->questionSets->find($questionSetId);
        if (null === $questionSet) {
            throw new ExerciseNotFoundException($exerciseUuid);
        }

        $questions = $questionSet->getOrderedQuestions();
        $submittedOptions = [];
        foreach ($answers as $i => $submittedIds) {
            $availableOptions = isset($questions[$i]) ? $questions[$i]->getOptions() : [];
            $submittedOptions[] = \array_values(\array_filter(
                $availableOptions,
                static fn (Option $option): bool => \in_array($option->getId(), $submittedIds, true),
            ));
        }

        $result = $this->grader->grade($questionSet, $submittedOptions);

        $this->attempts->save(new Attempt($exerciseUuid, $sessionId, $answers, $result->score, $result->total));

        return $result;
    }
}
