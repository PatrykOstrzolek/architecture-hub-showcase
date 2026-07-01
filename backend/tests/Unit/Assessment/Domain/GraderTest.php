<?php

declare(strict_types=1);

namespace App\Tests\Unit\Assessment\Domain;

use App\Assessment\Domain\Model\AnswerKey;
use App\Assessment\Domain\Service\Grader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

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
        $key = $this->answerKey([
            ['correct' => 'a', 'explanation' => null],
            ['correct' => 'b', 'explanation' => 'because b'],
        ]);

        $result = $this->grader->grade($key, ['a', 'b']);

        self::assertSame(2, $result->score);
        self::assertSame(2, $result->total);
        self::assertTrue($result->perQuestion[0]['isCorrect']);
        self::assertTrue($result->perQuestion[1]['isCorrect']);
        self::assertSame('because b', $result->perQuestion[1]['explanation']);
    }

    public function testAllWrongAnswersYieldZeroScore(): void
    {
        $key = $this->answerKey([
            ['correct' => 'a', 'explanation' => null],
            ['correct' => 'b', 'explanation' => null],
        ]);

        $result = $this->grader->grade($key, ['c', 'd']);

        self::assertSame(0, $result->score);
        self::assertFalse($result->perQuestion[0]['isCorrect']);
        self::assertFalse($result->perQuestion[1]['isCorrect']);
    }

    public function testUnansweredQuestionsCountAsIncorrect(): void
    {
        $key = $this->answerKey([
            ['correct' => 'a', 'explanation' => null],
            ['correct' => 'b', 'explanation' => null],
        ]);

        $result = $this->grader->grade($key, ['a', null]);

        self::assertSame(1, $result->score);
        self::assertTrue($result->perQuestion[0]['isCorrect']);
        self::assertFalse($result->perQuestion[1]['isCorrect']);
    }

    public function testMissingSubmittedAnswersAreTreatedAsUnanswered(): void
    {
        $key = $this->answerKey([
            ['correct' => 'a', 'explanation' => null],
            ['correct' => 'b', 'explanation' => null],
        ]);

        // Submitted answers array shorter than the question count.
        $result = $this->grader->grade($key, ['a']);

        self::assertSame(1, $result->score);
        self::assertSame(2, $result->total);
        self::assertFalse($result->perQuestion[1]['isCorrect']);
    }

    /**
     * @param list<array{correct: string, explanation: string|null}> $questions
     */
    private function answerKey(array $questions): AnswerKey
    {
        return new AnswerKey($questions);
    }
}
