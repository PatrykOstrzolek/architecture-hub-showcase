<?php

declare(strict_types=1);

namespace App\Tests\Unit\Assessment\Infrastructure\Sulu\Rest;

use App\Assessment\Domain\Model\Question;
use App\Assessment\Domain\Repository\QuestionRepositoryInterface;
use App\Assessment\Domain\Repository\QuestionSetRepositoryInterface;
use App\Assessment\Infrastructure\Cache\QuestionSetCacheKey;
use App\Assessment\Infrastructure\Sulu\Rest\QuestionController;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Cache\CacheInterface;

#[CoversClass(QuestionController::class)]
final class QuestionControllerTest extends TestCase
{
    private QuestionRepositoryInterface&MockObject $questions;
    private QuestionSetRepositoryInterface&MockObject $questionSets;
    private CacheInterface&MockObject $cache;
    private QuestionController $controller;

    protected function setUp(): void
    {
        $this->questions = $this->createMock(QuestionRepositoryInterface::class);
        $this->questionSets = $this->createMock(QuestionSetRepositoryInterface::class);
        $this->cache = $this->createMock(CacheInterface::class);
        $this->controller = new QuestionController($this->questions, $this->questionSets, $this->cache);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testCgetActionWithIdsFilterReturnsUnpaginatedFullSetAndNeverPaginates(): void
    {
        $questionOne = new Question('Q1', null);
        $questionTwo = new Question('Q2', null);

        $this->questions->expects(self::once())->method('findByIds')->with([1, 2])->willReturn([$questionOne, $questionTwo]);
        $this->questions->expects(self::never())->method('findPaginated');
        $this->questions->expects(self::never())->method('count');

        $response = $this->controller->cgetAction($this->queryRequest(['ids' => '1,2']));
        $body = $this->decode($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertArrayNotHasKey('page', $body);
        /** @var array<string, mixed> $embedded */
        $embedded = $body['_embedded'];
        /** @var list<mixed> $items */
        $items = $embedded['questions'];
        self::assertCount(2, $items);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testCgetActionWithoutIdsReturnsDefaultPaginatedRepresentation(): void
    {
        $questionOne = new Question('Q1', null);
        $questionTwo = new Question('Q2', null);

        $this->questions->expects(self::never())->method('findByIds');
        $this->questions->method('findPaginated')->with(1, 10)->willReturn([$questionOne, $questionTwo]);
        $this->questions->method('count')->willReturn(2);

        $response = $this->controller->cgetAction($this->queryRequest([]));
        $body = $this->decode($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(1, $body['page']);
        self::assertSame(10, $body['limit']);
        self::assertSame(2, $body['total']);
        /** @var array<string, mixed> $embedded */
        $embedded = $body['_embedded'];
        /** @var list<mixed> $items */
        $items = $embedded['questions'];
        self::assertCount(2, $items);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testCgetActionWithoutIdsAppliesExplicitPageAndLimit(): void
    {
        $question = new Question('Q1', null);

        $this->questions->method('findPaginated')->with(2, 5)->willReturn([$question]);
        $this->questions->method('count')->willReturn(11);

        $response = $this->controller->cgetAction($this->queryRequest(['page' => '2', 'limit' => '5']));
        $body = $this->decode($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(2, $body['page']);
        self::assertSame(5, $body['limit']);
        self::assertSame(11, $body['total']);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testCgetActionDefaultsGracefullyOnMalformedPageAndLimit(): void
    {
        $this->questions->method('findPaginated')->with(1, 10)->willReturn([]);
        $this->questions->method('count')->willReturn(0);

        $response = $this->controller->cgetAction($this->queryRequest(['page' => 'not-a-number', 'limit' => '-5']));
        $body = $this->decode($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(1, $body['page']);
        self::assertSame(10, $body['limit']);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testGetActionCallsFindWithOptionsNotFind(): void
    {
        $question = new Question('What is CAP?', 'CAP theorem explanation');
        $question->addOption('Consistency', true);

        $this->questions->expects(self::once())->method('findWithOptions')->with(1)->willReturn($question);
        $this->questions->expects(self::never())->method('find');

        $response = $this->controller->getAction(1);

        self::assertSame(200, $response->getStatusCode());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testGetActionReturnsQuestionWithOptionsAndCorrectness(): void
    {
        $question = new Question('What is CAP?', 'CAP theorem explanation');
        $question->addOption('Consistency', true);
        $question->addOption('Something else', false);

        $this->questions->method('findWithOptions')->with(1)->willReturn($question);

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
        $this->questions->method('findWithOptions')->willReturn(null);

        $response = $this->controller->getAction(999);

        self::assertSame(404, $response->getStatusCode());
    }

    #[AllowMockObjectsWithoutExpectations]
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

    #[AllowMockObjectsWithoutExpectations]
    public function testPostActionTriggersNoCacheInvalidation(): void
    {
        $this->questions->method('save');
        $this->questionSets->expects(self::never())->method('findQuestionSetIdsContaining');
        $this->cache->expects(self::never())->method('delete');

        $response = $this->controller->postAction($this->request([
            'text' => 'New question',
            'explanation' => null,
            'options' => [],
        ]));

        self::assertSame(200, $response->getStatusCode());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testPutActionRebuildsOptions(): void
    {
        $question = new Question('Old text', null);
        $question->addOption('Old option', true);

        $this->questions->method('findWithOptions')->with(1)->willReturn($question);
        $this->questions->expects(self::once())->method('save');
        $this->questionSets->method('findQuestionSetIdsContaining')->willReturn([]);

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

    public function testPutActionInvalidatesCacheForEveryQuestionSetContainingTheQuestion(): void
    {
        $question = new Question('Old text', null);

        $this->questions->method('findWithOptions')->with(1)->willReturn($question);
        $this->questions->method('save');
        $this->questionSets->expects(self::once())->method('findQuestionSetIdsContaining')->with(1)->willReturn([3, 7]);
        $this->cache->expects(self::exactly(2))->method('delete')
            ->with(self::callback(static fn (string $key): bool => \in_array($key, [
                QuestionSetCacheKey::for(3),
                QuestionSetCacheKey::for(7),
            ], true)));

        $response = $this->controller->putAction(1, $this->request([
            'text' => 'New text',
            'explanation' => null,
            'options' => [],
        ]));

        self::assertSame(200, $response->getStatusCode());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testDeleteActionRemovesQuestion(): void
    {
        $question = new Question('To delete', null);
        $this->questions->method('find')->with(1)->willReturn($question);
        $this->questions->expects(self::once())->method('remove')->with($question);
        $this->questionSets->method('findQuestionSetIdsContaining')->willReturn([]);

        $response = $this->controller->deleteAction(1);

        self::assertSame(204, $response->getStatusCode());
    }

    public function testDeleteActionInvalidatesCacheForEveryQuestionSetContainingTheQuestion(): void
    {
        $question = new Question('To delete', null);
        $this->questions->method('find')->with(1)->willReturn($question);
        $this->questions->expects(self::once())->method('remove')->with($question);
        $this->questionSets->expects(self::once())->method('findQuestionSetIdsContaining')->with(1)->willReturn([3, 7]);
        $this->cache->expects(self::exactly(2))->method('delete')
            ->with(self::callback(static fn (string $key): bool => \in_array($key, [
                QuestionSetCacheKey::for(3),
                QuestionSetCacheKey::for(7),
            ], true)));

        $response = $this->controller->deleteAction(1);

        self::assertSame(204, $response->getStatusCode());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testDeleteActionLooksUpContainingQuestionSetsBeforeRemovingTheQuestion(): void
    {
        // Regression test for the cache-invalidation ordering bug: assessment_question_set_item.question_id
        // is ON DELETE CASCADE, so findQuestionSetIdsContaining() MUST be called before remove() — calling it
        // after would always see an empty result, since the join rows are already gone. A test that only
        // stubs both methods unconditionally (like the sibling test above) cannot catch a regression here;
        // this test asserts the actual call order via a shared log.
        $question = new Question('To delete', null);
        $callOrder = [];

        $this->questions->method('find')->with(1)->willReturn($question);
        $this->questions->method('remove')->willReturnCallback(static function () use (&$callOrder): void {
            $callOrder[] = 'remove';
        });
        $this->questionSets->method('findQuestionSetIdsContaining')->willReturnCallback(
            static function () use (&$callOrder): array {
                $callOrder[] = 'findQuestionSetIdsContaining';

                return [3];
            },
        );

        $response = $this->controller->deleteAction(1);

        self::assertSame(204, $response->getStatusCode());
        self::assertSame(['findQuestionSetIdsContaining', 'remove'], $callOrder);
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

    /**
     * @param array<string, string> $query
     */
    private function queryRequest(array $query): Request
    {
        return new Request($query);
    }
}
