<?php

declare(strict_types=1);

namespace App\Tests\Functional\Assessment\Infrastructure\Sulu\Rest;

use App\Assessment\Domain\Model\Question;
use App\Assessment\Domain\Repository\QuestionRepositoryInterface;
use App\Assessment\Domain\Repository\QuestionSetRepositoryInterface;
use App\Assessment\Infrastructure\Cache\QuestionSetCacheKey;
use App\Assessment\Infrastructure\Sulu\Rest\QuestionController;
use Doctrine\ORM\EntityManagerInterface;
use FOS\RestBundle\View\ViewHandlerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * QuestionController now returns responses via Sulu's ViewHandlerInterface
 * (see the controller's docblock), which needs a real Request on the
 * container's RequestStack (format resolution) — and listAction's non-ids
 * path now runs Sulu's DoctrineListBuilder directly against the database,
 * bypassing QuestionRepositoryInterface entirely. Both make this a
 * functional/KernelTestCase test rather than a pure mock-based unit test.
 * The repository (and cache) are still mocked for every other action,
 * since those still go through QuestionRepositoryInterface/CacheInterface
 * unchanged — only the two "no ids filter" list tests seed real rows.
 */
#[CoversClass(QuestionController::class)]
final class QuestionControllerTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    private RequestStack $requestStack;

    private ViewHandlerInterface $viewHandler;

    private QuestionRepositoryInterface&MockObject $questions;

    private QuestionSetRepositoryInterface&MockObject $questionSets;

    private CacheInterface&MockObject $cache;

    /**
     * @var list<int>
     */
    private array $questionIds = [];

    protected function setUp(): void
    {
        self::bootKernel();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->entityManager = $entityManager;

        /** @var RequestStack $requestStack */
        $requestStack = self::getContainer()->get(RequestStack::class);
        $this->requestStack = $requestStack;

        /** @var ViewHandlerInterface $viewHandler */
        $viewHandler = self::getContainer()->get(ViewHandlerInterface::class);
        $this->viewHandler = $viewHandler;

        $this->questions = $this->createMock(QuestionRepositoryInterface::class);
        $this->questionSets = $this->createMock(QuestionSetRepositoryInterface::class);
        $this->cache = $this->createMock(CacheInterface::class);
    }

    protected function tearDown(): void
    {
        if ([] !== $this->questionIds) {
            $this->entityManager->getConnection()->executeStatement(
                \sprintf('DELETE FROM assessment_question WHERE id IN (%s)', \implode(',', $this->questionIds)),
            );
        }

        parent::tearDown();
    }

    private function controller(): QuestionController
    {
        /** @var \Sulu\Component\Rest\RestHelperInterface $restHelper */
        $restHelper = self::getContainer()->get(\Sulu\Component\Rest\RestHelperInterface::class);
        /** @var \Sulu\Component\Rest\ListBuilder\Metadata\FieldDescriptorFactoryInterface $fieldDescriptorFactory */
        $fieldDescriptorFactory = self::getContainer()->get(\Sulu\Component\Rest\ListBuilder\Metadata\FieldDescriptorFactoryInterface::class);
        /** @var \Sulu\Component\Rest\ListBuilder\Doctrine\DoctrineListBuilderFactoryInterface $listBuilderFactory */
        $listBuilderFactory = self::getContainer()->get(\Sulu\Component\Rest\ListBuilder\Doctrine\DoctrineListBuilderFactoryInterface::class);

        return new QuestionController(
            $this->questions,
            $this->questionSets,
            $this->cache,
            $restHelper,
            $fieldDescriptorFactory,
            $listBuilderFactory,
            $this->viewHandler,
        );
    }

    /**
     * @param array<string, string> $query
     */
    private function pushRequest(array $query = []): Request
    {
        $request = new Request($query);
        $request->setRequestFormat('json');
        $this->requestStack->push($request);

        return $request;
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testListActionWithIdsFilterReturnsUnpaginatedFullSetAndNeverPaginates(): void
    {
        // Goes through DoctrineListBuilder (Sulu's own setIds()/in() path,
        // matching TagController's flat=true branch), not the repository —
        // findByIds() is never called for this branch. Order isn't asserted
        // here: DoctrineListBuilder doesn't preserve requested id order, and
        // it doesn't need to — the Admin SPA's MultiSelectionStore.js
        // re-sorts by id client-side (verified by hand).
        $questionOne = new Question('Q1', null);
        $questionTwo = new Question('Q2', null);
        $this->entityManager->persist($questionOne);
        $this->entityManager->persist($questionTwo);
        $this->entityManager->flush();

        $idOne = $questionOne->getId();
        $idTwo = $questionTwo->getId();
        self::assertNotNull($idOne);
        self::assertNotNull($idTwo);
        $this->questionIds = [$idOne, $idTwo];

        $this->questions->expects(self::never())->method('findByIds');

        $request = $this->pushRequest(['ids' => "{$idOne},{$idTwo}"]);
        $response = $this->controller()->listAction($request);
        $body = $this->decode($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertArrayNotHasKey('page', $body);
        /** @var array<string, mixed> $embedded */
        $embedded = $body['_embedded'];
        /** @var list<array{id: int}> $items */
        $items = $embedded['questions'];
        $ids = \array_column($items, 'id');
        self::assertContains($idOne, $ids);
        self::assertContains($idTwo, $ids);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testListActionWithoutIdsReturnsDefaultPaginatedRepresentation(): void
    {
        $questionOne = new Question('Q1', null);
        $questionTwo = new Question('Q2', null);
        $this->entityManager->persist($questionOne);
        $this->entityManager->persist($questionTwo);
        $this->entityManager->flush();

        $idOne = $questionOne->getId();
        $idTwo = $questionTwo->getId();
        self::assertNotNull($idOne);
        self::assertNotNull($idTwo);
        $this->questionIds = [$idOne, $idTwo];

        $this->questions->expects(self::never())->method('findByIds');

        $request = $this->pushRequest(['limit' => '10']);
        $response = $this->controller()->listAction($request);
        $body = $this->decode($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(1, $body['page']);
        self::assertSame(10, $body['limit']);
        /** @var array<string, mixed> $embedded */
        $embedded = $body['_embedded'];
        /** @var list<array{id: int}> $items */
        $items = $embedded['questions'];
        $ids = \array_column($items, 'id');
        self::assertContains($idOne, $ids);
        self::assertContains($idTwo, $ids);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testListActionWithoutIdsAppliesExplicitPageAndLimit(): void
    {
        $question = new Question('Q1', null);
        $this->entityManager->persist($question);
        $this->entityManager->flush();

        $id = $question->getId();
        self::assertNotNull($id);
        $this->questionIds = [$id];

        $request = $this->pushRequest(['page' => '1', 'limit' => '1']);
        $response = $this->controller()->listAction($request);
        $body = $this->decode($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(1, $body['page']);
        self::assertSame(1, $body['limit']);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testListActionDefaultsGracefullyOnMalformedPageAndLimit(): void
    {
        // Regression test: Sulu's ListRestHelper passes page/limit straight
        // through with no validation — DoctrineListBuilder 500s on a
        // non-numeric value, confirmed by hand against a running admin.
        // QuestionController's ListPageAndLimitSanitizer::sanitize() call
        // must catch this before RestHelper ever sees it.
        $request = $this->pushRequest(['page' => 'not-a-number', 'limit' => '-5']);
        $response = $this->controller()->listAction($request);
        $body = $this->decode($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(1, $body['page']);
        self::assertSame(10, $body['limit']);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testListActionFallsBackToPlainListOnFullyMalformedIds(): void
    {
        // Regression test: Sulu's ListRestHelper::getIds() (used internally
        // by initializeListBuilder()) parses the raw `ids` query param
        // independently of IdListQueryParser, with only empty-string
        // filtering — no digit validation. A fully non-numeric `ids` value
        // reached DoctrineListBuilder's `WHERE id IN (...)` against an
        // integer column and 500'd with a Postgres type error, confirmed by
        // hand against a running admin. IdListQueryParser::
        // parseAndSanitizeRequest() must rewrite the request's `ids` param
        // before initializeListBuilder() reads it, so both parsers agree.
        $request = $this->pushRequest(['ids' => 'abc']);
        $response = $this->controller()->listAction($request);
        $body = $this->decode($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertArrayHasKey('page', $body, 'malformed ids should fall back to the plain paginated list, not the ids-filtered branch');
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testGetActionCallsFindWithOptionsNotFind(): void
    {
        $question = new Question('What is CAP?', 'CAP theorem explanation');
        $question->addOption('Consistency', true);

        $this->questions->expects(self::once())->method('findWithOptions')->with(1)->willReturn($question);
        $this->questions->expects(self::never())->method('find');

        $this->pushRequest();
        $response = $this->controller()->showAction(1);

        self::assertSame(200, $response->getStatusCode());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testGetActionReturnsQuestionWithOptionsAndCorrectness(): void
    {
        $question = new Question('What is CAP?', 'CAP theorem explanation');
        $question->addOption('Consistency', true);
        $question->addOption('Something else', false);

        $this->questions->method('findWithOptions')->with(1)->willReturn($question);

        $this->pushRequest();
        $response = $this->controller()->showAction(1);
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

        $this->pushRequest();
        $response = $this->controller()->showAction(999);

        self::assertSame(404, $response->getStatusCode());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testPostActionCreatesQuestionWithOptions(): void
    {
        $this->questions->expects(self::once())->method('save');

        $this->pushRequest();
        $response = $this->controller()->createAction($this->request([
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

        $this->pushRequest();
        $response = $this->controller()->createAction($this->request([
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

        $this->pushRequest();
        $response = $this->controller()->updateAction(1, $this->request([
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

        $this->pushRequest();
        $response = $this->controller()->updateAction(1, $this->request([
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

        $this->pushRequest();
        $response = $this->controller()->deleteAction(1);

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

        $this->pushRequest();
        $response = $this->controller()->deleteAction(1);

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

        $this->pushRequest();
        $response = $this->controller()->deleteAction(1);

        self::assertSame(204, $response->getStatusCode());
        self::assertSame(['findQuestionSetIdsContaining', 'remove'], $callOrder);
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Response $response): array
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
