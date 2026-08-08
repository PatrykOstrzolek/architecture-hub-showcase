<?php

declare(strict_types=1);

namespace App\Tests\Functional\Assessment\Infrastructure\Sulu\Rest;

use App\Assessment\Domain\Model\Question;
use App\Assessment\Domain\Model\QuestionSet;
use App\Assessment\Domain\Repository\QuestionRepositoryInterface;
use App\Assessment\Domain\Repository\QuestionSetRepositoryInterface;
use App\Assessment\Infrastructure\Cache\QuestionSetCacheKey;
use App\Assessment\Infrastructure\Sulu\Rest\QuestionSetController;
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
 * QuestionSetController now returns responses via Sulu's
 * ViewHandlerInterface (see the controller's docblock), which needs a real
 * Request on the container's RequestStack (format resolution) — and
 * listAction's non-ids path now runs Sulu's DoctrineListBuilder directly
 * against the database, bypassing QuestionSetRepositoryInterface entirely.
 * Both make this a functional/KernelTestCase test rather than a pure
 * mock-based unit test. The repositories (and cache) are still mocked for
 * every other action, since those still go through
 * QuestionSetRepositoryInterface/QuestionRepositoryInterface/CacheInterface
 * unchanged — only the two "no ids filter" list tests seed real rows.
 */
#[CoversClass(QuestionSetController::class)]
final class QuestionSetControllerTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    private RequestStack $requestStack;

    private ViewHandlerInterface $viewHandler;

    private QuestionSetRepositoryInterface&MockObject $questionSets;

    private QuestionRepositoryInterface&MockObject $questions;

    private CacheInterface&MockObject $cache;

    /**
     * @var list<int>
     */
    private array $questionSetIds = [];

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

        $this->questionSets = $this->createMock(QuestionSetRepositoryInterface::class);
        $this->questions = $this->createMock(QuestionRepositoryInterface::class);
        $this->cache = $this->createMock(CacheInterface::class);
    }

    protected function tearDown(): void
    {
        if ([] !== $this->questionSetIds) {
            $this->entityManager->getConnection()->executeStatement(
                \sprintf('DELETE FROM assessment_question_set WHERE id IN (%s)', \implode(',', $this->questionSetIds)),
            );
        }

        parent::tearDown();
    }

    private function controller(): QuestionSetController
    {
        /** @var \Sulu\Component\Rest\RestHelperInterface $restHelper */
        $restHelper = self::getContainer()->get(\Sulu\Component\Rest\RestHelperInterface::class);
        /** @var \Sulu\Component\Rest\ListBuilder\Metadata\FieldDescriptorFactoryInterface $fieldDescriptorFactory */
        $fieldDescriptorFactory = self::getContainer()->get(\Sulu\Component\Rest\ListBuilder\Metadata\FieldDescriptorFactoryInterface::class);
        /** @var \Sulu\Component\Rest\ListBuilder\Doctrine\DoctrineListBuilderFactoryInterface $listBuilderFactory */
        $listBuilderFactory = self::getContainer()->get(\Sulu\Component\Rest\ListBuilder\Doctrine\DoctrineListBuilderFactoryInterface::class);

        return new QuestionSetController(
            $this->questionSets,
            $this->questions,
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
        // QuestionSetRepositoryInterface::findByIds() was removed entirely
        // once this became its only caller. Order isn't asserted here:
        // DoctrineListBuilder doesn't preserve requested id order, and it
        // doesn't need to — the Admin SPA's MultiSelectionStore.js re-sorts
        // by id client-side (verified by hand).
        $setOne = new QuestionSet('Set One');
        $setTwo = new QuestionSet('Set Two');
        $this->entityManager->persist($setOne);
        $this->entityManager->persist($setTwo);
        $this->entityManager->flush();

        $idOne = $setOne->getId();
        $idTwo = $setTwo->getId();
        self::assertNotNull($idOne);
        self::assertNotNull($idTwo);
        $this->questionSetIds = [$idOne, $idTwo];

        $request = $this->pushRequest(['ids' => "{$idOne},{$idTwo}"]);
        $response = $this->controller()->listAction($request);
        $body = $this->decode($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertArrayNotHasKey('page', $body);
        /** @var array<string, mixed> $embedded */
        $embedded = $body['_embedded'];
        /** @var list<array{id: int}> $items */
        $items = $embedded['question_sets'];
        $ids = \array_column($items, 'id');
        self::assertContains($idOne, $ids);
        self::assertContains($idTwo, $ids);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testListActionWithoutIdsReturnsDefaultPaginatedRepresentation(): void
    {
        $setOne = new QuestionSet('Set One');
        $setTwo = new QuestionSet('Set Two');
        $this->entityManager->persist($setOne);
        $this->entityManager->persist($setTwo);
        $this->entityManager->flush();

        $idOne = $setOne->getId();
        $idTwo = $setTwo->getId();
        self::assertNotNull($idOne);
        self::assertNotNull($idTwo);
        $this->questionSetIds = [$idOne, $idTwo];

        $request = $this->pushRequest(['limit' => '10']);
        $response = $this->controller()->listAction($request);
        $body = $this->decode($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(1, $body['page']);
        self::assertSame(10, $body['limit']);
        /** @var array<string, mixed> $embedded */
        $embedded = $body['_embedded'];
        /** @var list<array{id: int}> $items */
        $items = $embedded['question_sets'];
        $ids = \array_column($items, 'id');
        self::assertContains($idOne, $ids);
        self::assertContains($idTwo, $ids);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testListActionWithoutIdsAppliesExplicitPageAndLimit(): void
    {
        $set = new QuestionSet('Set One');
        $this->entityManager->persist($set);
        $this->entityManager->flush();

        $id = $set->getId();
        self::assertNotNull($id);
        $this->questionSetIds = [$id];

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
        // QuestionSetController's ListPageAndLimitSanitizer::sanitize() call
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
    public function testGetActionReturnsOrderedQuestionIds(): void
    {
        $set = new QuestionSet('Quiz');
        $q1 = new Question('Q1', null);
        $q2 = new Question('Q2', null);
        $set->addQuestion($q1, 0);
        $set->addQuestion($q2, 1);

        $this->questionSets->method('findWithQuestions')->with(1)->willReturn($set);

        $this->pushRequest();
        $response = $this->controller()->showAction(1);
        $body = $this->decode($response);

        self::assertSame('Quiz', $body['title']);
        self::assertSame([$q1->getId(), $q2->getId()], $body['questionIds']);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testGetActionReturns404ForUnknownSet(): void
    {
        $this->questionSets->method('findWithQuestions')->willReturn(null);

        $this->pushRequest();
        $response = $this->controller()->showAction(999);

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

        $this->pushRequest();
        $response = $this->controller()->createAction($this->request([
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

    #[AllowMockObjectsWithoutExpectations]
    public function testSameQuestionCanBeAttachedToTwoDifferentSets(): void
    {
        $sharedQuestion = new Question('Shared question', null);
        $this->questions->method('findByIds')->with([7])->willReturn([$sharedQuestion]);
        $this->questionSets->expects(self::exactly(2))->method('save');

        $this->pushRequest();
        $responseOne = $this->controller()->createAction($this->request(['title' => 'Set One', 'questionIds' => [7]]));
        $responseTwo = $this->controller()->createAction($this->request(['title' => 'Set Two', 'questionIds' => [7]]));

        self::assertSame(200, $responseOne->getStatusCode());
        self::assertSame(200, $responseTwo->getStatusCode());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testPostActionIgnoresUnresolvableQuestionIds(): void
    {
        $this->questions->method('findByIds')->willReturn([]);
        $this->questionSets->expects(self::once())->method('save');

        $this->pushRequest();
        $response = $this->controller()->createAction($this->request([
            'title' => 'Quiz',
            'questionIds' => [404],
        ]));

        $body = $this->decode($response);

        self::assertSame([], $body['questionIds']);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testPostActionDeletesCacheEntryForNewlyAssignedId(): void
    {
        $this->questions->method('findByIds')->willReturn([]);
        $this->questionSets->method('save')->willReturnCallback(
            function (QuestionSet $questionSet): void {
                $this->assignId($questionSet, 5);
            },
        );
        $this->cache->expects(self::once())->method('delete')->with(QuestionSetCacheKey::for(5));

        $this->pushRequest();
        $response = $this->controller()->createAction($this->request(['title' => 'Quiz', 'questionIds' => []]));

        self::assertSame(200, $response->getStatusCode());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testPutActionDeletesCacheEntryForQuestionSetId(): void
    {
        $set = new QuestionSet('Original title');
        $this->assignId($set, 3);
        $this->questionSets->method('findWithQuestions')->with(3)->willReturn($set);
        $this->questions->method('findByIds')->willReturn([]);
        $this->cache->expects(self::once())->method('delete')->with(QuestionSetCacheKey::for(3));

        $this->pushRequest();
        $response = $this->controller()->updateAction(3, $this->request(['title' => 'Updated title', 'questionIds' => []]));

        self::assertSame(200, $response->getStatusCode());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testDeleteActionRemovesQuestionSet(): void
    {
        $set = new QuestionSet('To delete');
        $this->questionSets->method('find')->with(1)->willReturn($set);
        $this->questionSets->expects(self::once())->method('remove')->with($set);
        $this->cache->expects(self::once())->method('delete')->with(QuestionSetCacheKey::for(1));

        $this->pushRequest();
        $response = $this->controller()->deleteAction(1);

        self::assertSame(204, $response->getStatusCode());
    }

    private function assignId(QuestionSet $questionSet, int $id): void
    {
        $property = new \ReflectionProperty($questionSet, 'id');
        $property->setValue($questionSet, $id);
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
