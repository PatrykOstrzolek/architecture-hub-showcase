<?php

declare(strict_types=1);

namespace App\Tests\Unit\Assessment\Domain;

use App\Assessment\Domain\Model\Option;
use App\Assessment\Domain\Model\Question;
use App\Assessment\Domain\Model\QuestionSet;
use App\Assessment\Domain\Service\Grader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Grader compares Option *object references* (identity), not database ids —
 * ids are a persistence-layer surrogate key that doesn't exist until a real
 * flush, and comparing by identity is what makes this pure/testable without
 * a database. GradeResult still surfaces ids in its output for API callers.
 */
#[CoversClass(Grader::class)]
final class GraderTest extends TestCase
{
    private Grader $grader;

    protected function setUp(): void
    {
        $this->grader = new Grader();
    }

    public function testAllCorrectAnswersYieldFullScore(): void
    {
        $set = new QuestionSet('Quiz');
        $q1 = new Question('Q1', null);
        $correct1 = $q1->addOption('a', true);
        $set->addQuestion($q1);

        $q2 = new Question('Q2', 'because b');
        $correct2 = $q2->addOption('b', true);
        $set->addQuestion($q2);

        $result = $this->grader->grade($set, [[$correct1], [$correct2]]);

        self::assertSame(2, $result->score);
        self::assertSame(2, $result->total);
        self::assertTrue($result->perQuestion[0]['isCorrect']);
        self::assertTrue($result->perQuestion[1]['isCorrect']);
        self::assertSame('because b', $result->perQuestion[1]['explanation']);
    }

    public function testAllWrongAnswersYieldZeroScore(): void
    {
        $set = new QuestionSet('Quiz');
        $q1 = new Question('Q1', null);
        $q1->addOption('a', true);
        $wrong1 = $q1->addOption('c', false);
        $set->addQuestion($q1);

        $q2 = new Question('Q2', null);
        $q2->addOption('b', true);
        $wrong2 = $q2->addOption('d', false);
        $set->addQuestion($q2);

        $result = $this->grader->grade($set, [[$wrong1], [$wrong2]]);

        self::assertSame(0, $result->score);
        self::assertFalse($result->perQuestion[0]['isCorrect']);
        self::assertFalse($result->perQuestion[1]['isCorrect']);
    }

    public function testUnansweredQuestionsCountAsIncorrect(): void
    {
        $set = new QuestionSet('Quiz');
        $q1 = new Question('Q1', null);
        $correct1 = $q1->addOption('a', true);
        $set->addQuestion($q1);

        $q2 = new Question('Q2', null);
        $q2->addOption('b', true);
        $set->addQuestion($q2);

        $result = $this->grader->grade($set, [[$correct1], []]);

        self::assertSame(1, $result->score);
        self::assertTrue($result->perQuestion[0]['isCorrect']);
        self::assertFalse($result->perQuestion[1]['isCorrect']);
    }

    public function testMissingSubmittedAnswerSetsAreTreatedAsUnanswered(): void
    {
        $set = new QuestionSet('Quiz');
        $q1 = new Question('Q1', null);
        $correct1 = $q1->addOption('a', true);
        $set->addQuestion($q1);

        $q2 = new Question('Q2', null);
        $q2->addOption('b', true);
        $set->addQuestion($q2);

        // Submitted answers array shorter than the question count.
        $result = $this->grader->grade($set, [[$correct1]]);

        self::assertSame(1, $result->score);
        self::assertSame(2, $result->total);
        self::assertFalse($result->perQuestion[1]['isCorrect']);
    }

    public function testExactMultiCorrectSelectionIsCorrect(): void
    {
        $set = new QuestionSet('Quiz');
        $question = new Question('Select all that apply', null);
        $correctOne = $question->addOption('Right 1', true);
        $correctTwo = $question->addOption('Right 2', true);
        $question->addOption('Wrong', false);
        $set->addQuestion($question);

        $result = $this->grader->grade($set, [[$correctOne, $correctTwo]]);

        self::assertSame(1, $result->score);
        self::assertTrue($result->perQuestion[0]['isCorrect']);
    }

    public function testPartialMultiCorrectSelectionIsIncorrect(): void
    {
        $set = new QuestionSet('Quiz');
        $question = new Question('Select all that apply', null);
        $correctOne = $question->addOption('Right 1', true);
        $question->addOption('Right 2', true);
        $question->addOption('Wrong', false);
        $set->addQuestion($question);

        // Only one of the two correct options selected — a strict subset.
        $result = $this->grader->grade($set, [[$correctOne]]);

        self::assertSame(0, $result->score);
        self::assertFalse($result->perQuestion[0]['isCorrect']);
    }

    public function testExtraWrongOptionAlongsideAllCorrectOptionsIsIncorrect(): void
    {
        $set = new QuestionSet('Quiz');
        $question = new Question('Select all that apply', null);
        $correctOne = $question->addOption('Right 1', true);
        $correctTwo = $question->addOption('Right 2', true);
        $wrong = $question->addOption('Wrong', false);
        $set->addQuestion($question);

        $result = $this->grader->grade($set, [[$correctOne, $correctTwo, $wrong]]);

        self::assertSame(0, $result->score);
        self::assertFalse($result->perQuestion[0]['isCorrect']);
    }
}
