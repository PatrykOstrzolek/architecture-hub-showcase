<?php

declare(strict_types=1);

namespace App\Tests\Unit\Assessment\Infrastructure\Sulu\Rest;

use App\Assessment\Domain\Model\Question;
use App\Assessment\Domain\Model\QuestionSet;
use App\Assessment\Domain\Repository\QuestionRepositoryInterface;
use App\Assessment\Domain\Repository\QuestionSetRepositoryInterface;
use App\Assessment\Infrastructure\Sulu\Rest\QuestionSetController;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(QuestionSetController::class)]
final class QuestionSetControllerTest extends TestCase
{
    private QuestionSetRepositoryInterface&MockObject $questionSets;
    private QuestionRepositoryInterface&MockObject $questions;
    private QuestionSetController $controller;

    protected function setUp(): void
    {
        $this->questionSets = $this->createMock(QuestionSetRepositoryInterface::class);
        $this->questions = $this->createMock(QuestionRepositoryInterface::class);
        $this->controller = new QuestionSetController($this->questionSets, $this->questions);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testGetActionReturnsOrderedQuestionIds(): void
    {
        $set = new QuestionSet('Quiz');
        $q1 = new Question('Q1', null);
        $q2 = new Question('Q2', null);
        $set->addQuestion($q1, 0);
        $set->addQuestion($q2, 1);

        $this->questionSets->method('findWithQuestions')->with(1)->willReturn($set);

        $response = $this->controller->getAction(1);
        $body = $this->decode($response);

        self::assertSame('Quiz', $body['title']);
        self::assertSame([$q1->getId(), $q2->getId()], $body['questionIds']);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testGetActionReturns404ForUnknownSet(): void
    {
        $this->questionSets->method('findWithQuestions')->willReturn(null);

        $response = $this->controller->getAction(999);

        self::assertSame(404, $response->getStatusCode());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testPostActionAttachesExistingQuestionsInOrder(): void
    {
        $questionOne = new Question('Q1', null);
        $questionTwo = new Question('Q2', null);

        $this->questions->method('findByIds')->with([1, 2])->willReturn([$questionOne, $questionTwo]);

        $saved = null;
        $this->questionSets->expects(self::once())->method('save')
            ->with(self::callback(static function (QuestionSet $questionSet) use (&$saved): bool {
                $saved = $questionSet;

                return true;
            }));

        $response = $this->controller->postAction($this->request([
            'title' => 'New Quiz',
            'questionIds' => [1, 2],
        ]));

        $body = $this->decode($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('New Quiz', $body['title']);
        /** @var list<mixed> $questionIds */
        $questionIds = $body['questionIds'];
        self::assertCount(2, $questionIds);
        self::assertSame([$questionOne, $questionTwo], $saved?->getOrderedQuestions());
    }

    public function testSameQuestionCanBeAttachedToTwoDifferentSets(): void
    {
        $sharedQuestion = new Question('Shared question', null);
        $this->questions->method('findByIds')->with([7])->willReturn([$sharedQuestion]);
        $this->questionSets->expects(self::exactly(2))->method('save');

        $responseOne = $this->controller->postAction($this->request(['title' => 'Set One', 'questionIds' => [7]]));
        $responseTwo = $this->controller->postAction($this->request(['title' => 'Set Two', 'questionIds' => [7]]));

        self::assertSame(200, $responseOne->getStatusCode());
        self::assertSame(200, $responseTwo->getStatusCode());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testPostActionIgnoresUnresolvableQuestionIds(): void
    {
        $this->questions->method('findByIds')->willReturn([]);
        $this->questionSets->expects(self::once())->method('save');

        $response = $this->controller->postAction($this->request([
            'title' => 'Quiz',
            'questionIds' => [404],
        ]));

        $body = $this->decode($response);

        self::assertSame([], $body['questionIds']);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testDeleteActionRemovesQuestionSet(): void
    {
        $set = new QuestionSet('To delete');
        $this->questionSets->method('find')->with(1)->willReturn($set);
        $this->questionSets->expects(self::once())->method('remove')->with($set);

        $response = $this->controller->deleteAction(1);

        self::assertSame(204, $response->getStatusCode());
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(JsonResponse $response): array
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
