<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller\Website;

use App\Assessment\Application\SubmitAttemptService;
use App\Assessment\Domain\Exception\ExerciseNotFoundException;
use App\Assessment\Domain\Model\GradeResult;
use App\Controller\Website\ExerciseAttemptController;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(ExerciseAttemptController::class)]
final class ExerciseAttemptControllerTest extends TestCase
{
    private const VALID_UUID = '11111111-1111-1111-1111-111111111111';
    private const VALID_SESSION = '22222222-2222-2222-2222-222222222222';

    private SubmitAttemptService&MockObject $submitAttemptService;
    private ExerciseAttemptController $controller;

    protected function setUp(): void
    {
        $this->submitAttemptService = $this->createMock(SubmitAttemptService::class);
        $this->controller = new ExerciseAttemptController($this->submitAttemptService);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testValidSubmissionReturnsGradeResult(): void
    {
        $this->submitAttemptService->method('submit')->willReturn(
            new GradeResult(1, 2, [
                ['correct' => 'a', 'isCorrect' => true, 'explanation' => null],
                ['correct' => 'b', 'isCorrect' => false, 'explanation' => 'because b'],
            ])
        );

        $response = $this->controller->postAction($this->request([
            'exerciseUuid' => self::VALID_UUID,
            'sessionId' => self::VALID_SESSION,
            'answers' => ['a', 'c'],
        ]));

        /** @var array{score: mixed, total: mixed, results: list<mixed>} $body */
        $body = \json_decode((string) $response->getContent(), true);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(1, $body['score']);
        self::assertSame(2, $body['total']);
        self::assertCount(2, $body['results']);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testUnknownExerciseReturns404(): void
    {
        $this->submitAttemptService->method('submit')->willThrowException(
            new ExerciseNotFoundException(self::VALID_UUID)
        );

        $response = $this->controller->postAction($this->request([
            'exerciseUuid' => self::VALID_UUID,
            'sessionId' => self::VALID_SESSION,
            'answers' => [],
        ]));

        self::assertSame(404, $response->getStatusCode());
    }

    public function testMalformedUuidReturns400WithoutCallingService(): void
    {
        $this->submitAttemptService->expects(self::never())->method('submit');

        $response = $this->controller->postAction($this->request([
            'exerciseUuid' => 'not-a-uuid',
            'sessionId' => self::VALID_SESSION,
            'answers' => [],
        ]));

        self::assertSame(400, $response->getStatusCode());
    }

    public function testAnswersWithInvalidOptionReturns400(): void
    {
        $this->submitAttemptService->expects(self::never())->method('submit');

        $response = $this->controller->postAction($this->request([
            'exerciseUuid' => self::VALID_UUID,
            'sessionId' => self::VALID_SESSION,
            'answers' => ['z'],
        ]));

        self::assertSame(400, $response->getStatusCode());
    }

    public function testTooManyAnswersReturns400(): void
    {
        $this->submitAttemptService->expects(self::never())->method('submit');

        $response = $this->controller->postAction($this->request([
            'exerciseUuid' => self::VALID_UUID,
            'sessionId' => self::VALID_SESSION,
            'answers' => \array_fill(0, 21, 'a'),
        ]));

        self::assertSame(400, $response->getStatusCode());
    }

    public function testNonJsonBodyReturns400(): void
    {
        $this->submitAttemptService->expects(self::never())->method('submit');

        $response = $this->controller->postAction(new Request([], [], [], [], [], [], 'not-json'));

        self::assertSame(400, $response->getStatusCode());
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function request(array $payload): Request
    {
        return new Request([], [], [], [], [], [], (string) \json_encode($payload));
    }
}
