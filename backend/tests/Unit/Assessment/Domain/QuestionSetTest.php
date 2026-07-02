<?php

declare(strict_types=1);

namespace App\Tests\Unit\Assessment\Domain;

use App\Assessment\Domain\Model\Question;
use App\Assessment\Domain\Model\QuestionSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(QuestionSet::class)]
final class QuestionSetTest extends TestCase
{
    public function testGetOrderedQuestionsReturnsQuestionsSortedByItemPosition(): void
    {
        $questionA = new Question('What is CAP?', null);
        $questionB = new Question('What is DDD?', null);
        $questionC = new Question('What is CQRS?', null);

        $set = new QuestionSet('Distributed Systems Quiz');
        // Added out of intended display order on purpose.
        $set->addQuestion($questionC, 2);
        $set->addQuestion($questionA, 0);
        $set->addQuestion($questionB, 1);

        self::assertSame(
            [$questionA, $questionB, $questionC],
            $set->getOrderedQuestions(),
        );
    }

    public function testGetOrderedQuestionsReturnsEmptyListForNewSet(): void
    {
        $set = new QuestionSet('Empty Quiz');

        self::assertSame([], $set->getOrderedQuestions());
    }

    public function testSameQuestionCanBelongToMultipleSets(): void
    {
        $question = new Question('Shared question', null);

        $setOne = new QuestionSet('Set One');
        $setOne->addQuestion($question, 0);

        $setTwo = new QuestionSet('Set Two');
        $setTwo->addQuestion($question, 0);

        self::assertSame([$question], $setOne->getOrderedQuestions());
        self::assertSame([$question], $setTwo->getOrderedQuestions());
    }
}
