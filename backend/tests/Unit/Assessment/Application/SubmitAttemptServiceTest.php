<?php

declare(strict_types=1);

namespace App\Tests\Unit\Assessment\Application;

use App\Assessment\Application\Port\ExerciseQuestionSetLocatorInterface;
use App\Assessment\Application\SubmitAttemptService;
use App\Assessment\Domain\Exception\ExerciseNotFoundException;
use App\Assessment\Domain\Model\Question;
use App\Assessment\Domain\Model\QuestionSet;
use App\Assessment\Domain\Repository\AttemptRepositoryInterface;
use App\Assessment\Domain\Repository\QuestionSetRepositoryInterface;
use App\Assessment\Domain\Service\Grader;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(SubmitAttemptService::class)]
final class SubmitAttemptServiceTest extends TestCase
{
    private const PAGE_UUID = '11111111-1111-1111-1111-111111111111';
    private const SESSION_ID = '22222222-2222-2222-2222-222222222222';

    private ExerciseQuestionSetLocatorInterface&MockObject $locator;
    private QuestionSetRepositoryInterface&MockObject $questionSets;
    private AttemptRepositoryInterface&MockObject $attempts;
    private SubmitAttemptService $service;

    protected function setUp(): void
    {
        $this->locator = $this->createMock(ExerciseQuestionSetLocatorInterface::class);
        $this->questionSets = $this->createMock(QuestionSetRepositoryInterface::class);
        $this->attempts = $this->createMock(AttemptRepositoryInterface::class);
        $this->service = new SubmitAttemptService($this->locator, $this->questionSets, new Grader(), $this->attempts);
    }

    public function testSubmitResolvesQuestionSetGradesAndSavesAttempt(): void
    {
        $questionSet = new QuestionSet('Quiz');
        $question = new Question('Q1', null);
        $correct = $question->addOption('a', true);
        $questionSet->addQuestion($question);

        // Doctrine only assigns ids on a real flush; assign one here so the
        // service's id -> Option resolution has something real to match
        // against, without needing a database for this pure unit test.
        $this->assignId($correct, 101);

        $this->locator->method('findQuestionSetId')->with(self::PAGE_UUID)->willReturn(42);
        $this->questionSets->method('find')->with(42)->willReturn($questionSet);
        $this->attempts->expects(self::once())->method('save');

        $result = $this->service->submit(self::PAGE_UUID, self::SESSION_ID, [[101]]);

        self::assertSame(1, $result->score);
        self::assertSame(1, $result->total);
        self::assertTrue($result->perQuestion[0]['isCorrect']);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testSubmitTreatsUnresolvableOptionIdsAsUnanswered(): void
    {
        $questionSet = new QuestionSet('Quiz');
        $question = new Question('Q1', null);
        $correct = $question->addOption('a', true);
        $questionSet->addQuestion($question);
        $this->assignId($correct, 101);

        $this->locator->method('findQuestionSetId')->willReturn(42);
        $this->questionSets->method('find')->willReturn($questionSet);
        $this->attempts->expects(self::once())->method('save');

        // 999 doesn't match any real Option id on this QuestionSet.
        $result = $this->service->submit(self::PAGE_UUID, self::SESSION_ID, [[999]]);

        self::assertSame(0, $result->score);
        self::assertFalse($result->perQuestion[0]['isCorrect']);
    }

    private function assignId(object $entity, int $id): void
    {
        $property = new \ReflectionProperty($entity, 'id');
        $property->setValue($entity, $id);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testUnknownExerciseThrowsWhenLocatorFindsNoQuestionSet(): void
    {
        $this->locator->method('findQuestionSetId')->willReturn(null);
        $this->questionSets->expects(self::never())->method('find');
        $this->attempts->expects(self::never())->method('save');

        $this->expectException(ExerciseNotFoundException::class);

        $this->service->submit(self::PAGE_UUID, self::SESSION_ID, []);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testUnknownExerciseThrowsWhenQuestionSetRepositoryFindsNothing(): void
    {
        $this->locator->method('findQuestionSetId')->willReturn(42);
        $this->questionSets->method('find')->with(42)->willReturn(null);
        $this->attempts->expects(self::never())->method('save');

        $this->expectException(ExerciseNotFoundException::class);

        $this->service->submit(self::PAGE_UUID, self::SESSION_ID, []);
    }
}
