<?php

declare(strict_types=1);

namespace App\Tests\Unit\Assessment\Infrastructure\Sulu\Rest;

use App\Assessment\Domain\Model\Question;
use App\Assessment\Domain\Repository\QuestionRepositoryInterface;
use App\Assessment\Infrastructure\Sulu\Rest\QuestionController;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(QuestionController::class)]
final class QuestionControllerTest extends TestCase
{
    private QuestionRepositoryInterface&MockObject $questions;
    private QuestionController $controller;

    protected function setUp(): void
    {
        $this->questions = $this->createMock(QuestionRepositoryInterface::class);
        $this->controller = new QuestionController($this->questions);
    }

    public function testGetActionReturnsQuestionWithOptionsAndCorrectness(): void
    {
        $question = new Question('What is CAP?', 'CAP theorem explanation');
        $question->addOption('Consistency', true);
        $question->addOption('Something else', false);

        $this->questions->method('find')->with(1)->willReturn($question);

        $response = $this->controller->getAction(1);
        $body = $this->decode($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('What is CAP?', $body['text']);
        self::assertSame('CAP theorem explanation', $body['explanation']);

        /** @var list<array{isCorrect: bool}> $options */
        $options = $body['options'];
        self::assertCount(2, $options);
        self::assertTrue($options[0]['isCorrect']);
        self::assertFalse($options[1]['isCorrect']);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testGetActionReturns404ForUnknownQuestion(): void
    {
        $this->questions->method('find')->willReturn(null);

        $response = $this->controller->getAction(999);

        self::assertSame(404, $response->getStatusCode());
    }

    public function testPostActionCreatesQuestionWithOptions(): void
    {
        $this->questions->expects(self::once())->method('save');

        $response = $this->controller->postAction($this->request([
            'text' => 'What is DDD?',
            'explanation' => null,
            'options' => [
                ['text' => 'Domain-Driven Design', 'isCorrect' => true],
                ['text' => 'Data-Driven Design', 'isCorrect' => false],
            ],
        ]));

        $body = $this->decode($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('What is DDD?', $body['text']);
        /** @var list<mixed> $options */
        $options = $body['options'];
        self::assertCount(2, $options);
    }

    public function testPutActionRebuildsOptions(): void
    {
        $question = new Question('Old text', null);
        $question->addOption('Old option', true);

        $this->questions->method('find')->with(1)->willReturn($question);
        $this->questions->expects(self::once())->method('save');

        $response = $this->controller->putAction(1, $this->request([
            'text' => 'New text',
            'explanation' => 'New explanation',
            'options' => [
                ['text' => 'New option A', 'isCorrect' => false],
                ['text' => 'New option B', 'isCorrect' => true],
            ],
        ]));

        $body = $this->decode($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('New text', $body['text']);
        /** @var list<array{text: string}> $options */
        $options = $body['options'];
        self::assertCount(2, $options);
        self::assertSame('New option A', $options[0]['text']);
    }

    public function testDeleteActionRemovesQuestion(): void
    {
        $question = new Question('To delete', null);
        $this->questions->method('find')->with(1)->willReturn($question);
        $this->questions->expects(self::once())->method('remove')->with($question);

        $response = $this->controller->deleteAction(1);

        self::assertSame(204, $response->getStatusCode());
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(\Symfony\Component\HttpFoundation\JsonResponse $response): array
    {
        /** @var array<string, mixed> $body */
        $body = \json_decode((string) $response->getContent(), true);

        return $body;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function request(array $payload): Request
    {
        return new Request([], [], [], [], [], [], (string) \json_encode($payload));
    }
}
