<?php

declare(strict_types=1);

namespace App\Tests\Functional\Assessment\Infrastructure\Sulu\Rest;

use App\Assessment\Domain\Model\Attempt;
use App\Assessment\Infrastructure\Sulu\Rest\AttemptController;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * listAction now goes through Sulu's native DoctrineListBuilder + JMS
 * Serializer-backed ViewHandler (see AttemptController), which can't be
 * exercised against a mocked repository the way BuildsPaginatedRepresentation
 * could — it builds and runs its own query, and RestHelper reads page/limit
 * off the container's RequestStack rather than a method argument (Sulu's own
 * convention, matching TagController). This is a functional/KernelTestCase
 * test for that reason, following DoctrineQuestionSetRepositoryTest's
 * inline-fixture-plus-manual-cleanup convention (no DataFixtures/purge
 * convention exists yet in this codebase).
 */
#[CoversClass(AttemptController::class)]
final class AttemptControllerTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    private RequestStack $requestStack;

    private AttemptController $controller;

    /**
     * @var list<int>
     */
    private array $attemptIds = [];

    protected function setUp(): void
    {
        self::bootKernel();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->entityManager = $entityManager;

        /** @var RequestStack $requestStack */
        $requestStack = self::getContainer()->get(RequestStack::class);
        $this->requestStack = $requestStack;

        /** @var AttemptController $controller */
        $controller = self::getContainer()->get(AttemptController::class);
        $this->controller = $controller;
    }

    /**
     * @param array<string, string> $query
     */
    private function pushRequest(array $query = []): Request
    {
        $request = new Request($query);
        // ViewHandler resolves the response format off the current request
        // (real requests carry this via Accept/Content-Type negotiation);
        // a synthetic Request defaults to 'html', so it's set explicitly here.
        $request->setRequestFormat('json');
        $this->requestStack->push($request);

        return $request;
    }

    protected function tearDown(): void
    {
        if ([] !== $this->attemptIds) {
            $this->entityManager->getConnection()->executeStatement(
                \sprintf('DELETE FROM exercise_attempt WHERE id IN (%s)', \implode(',', $this->attemptIds)),
            );
        }

        parent::tearDown();
    }

    public function testListActionReturnsPaginatedAttemptsOrderedByCreatedAtDescending(): void
    {
        $older = new Attempt('exercise-1', 'session-1', [[1]], 1, 1);
        $newer = new Attempt('exercise-2', 'session-2', [[0]], 0, 1);

        $this->entityManager->persist($older);
        $this->entityManager->persist($newer);
        $this->entityManager->flush();

        $olderId = $older->getId();
        $newerId = $newer->getId();
        self::assertNotNull($olderId);
        self::assertNotNull($newerId);
        $this->attemptIds = [$olderId, $newerId];

        $this->entityManager->clear();

        $request = $this->pushRequest(['limit' => '10']);
        $response = $this->controller->listAction($request);
        $body = $this->decode($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(1, $body['page']);
        self::assertSame(10, $body['limit']);

        /** @var array<string, mixed> $embedded */
        $embedded = $body['_embedded'];
        /** @var list<array{id: int, exerciseUuid: string, sessionId: string}> $items */
        $items = $embedded['attempts'];

        $ids = \array_column($items, 'id');
        self::assertContains($olderId, $ids);
        self::assertContains($newerId, $ids);
    }

    public function testListActionAppliesExplicitPageAndLimit(): void
    {
        $attempt = new Attempt('exercise-paged', 'session-paged', [[1]], 1, 1);
        $this->entityManager->persist($attempt);
        $this->entityManager->flush();

        $attemptId = $attempt->getId();
        self::assertNotNull($attemptId);
        $this->attemptIds = [$attemptId];

        $request = $this->pushRequest(['page' => '1', 'limit' => '1']);
        $response = $this->controller->listAction($request);
        $body = $this->decode($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(1, $body['page']);
        self::assertSame(1, $body['limit']);

        /** @var array<string, mixed> $embedded */
        $embedded = $body['_embedded'];
        /** @var list<mixed> $items */
        $items = $embedded['attempts'];
        self::assertCount(1, $items);
    }

    public function testListActionDefaultsGracefullyOnMalformedPageAndLimit(): void
    {
        // Regression test: Sulu's ListRestHelper passes page/limit straight
        // through with no validation — DoctrineListBuilder 500s on a
        // non-numeric value ("Unsupported operand types: string - int"),
        // confirmed by hand against a running admin. AttemptController's
        // ListPageAndLimitSanitizer::sanitize() call must catch this before
        // RestHelper ever sees it.
        $request = $this->pushRequest(['page' => 'not-a-number', 'limit' => '-5']);
        $response = $this->controller->listAction($request);
        $body = $this->decode($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(1, $body['page']);
        self::assertSame(10, $body['limit']);
    }

    public function testDeleteActionRemovesAttempt(): void
    {
        $attempt = new Attempt('exercise-delete', 'session-delete', [[1]], 1, 1);
        $this->entityManager->persist($attempt);
        $this->entityManager->flush();

        $attemptId = $attempt->getId();
        self::assertNotNull($attemptId);

        $this->pushRequest();
        $response = $this->controller->deleteAction($attemptId);

        self::assertSame(204, $response->getStatusCode());
        self::assertNull($this->entityManager->find(Attempt::class, $attemptId));
    }

    public function testDeleteActionReturns404ForUnknownAttempt(): void
    {
        $this->pushRequest();
        $response = $this->controller->deleteAction(999_999_999);

        self::assertSame(404, $response->getStatusCode());
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(\Symfony\Component\HttpFoundation\Response $response): array
    {
        /** @var array<string, mixed> $body */
        $body = \json_decode((string) $response->getContent(), true);

        return $body;
    }
}
