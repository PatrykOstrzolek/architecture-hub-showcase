<?php

declare(strict_types=1);

namespace App\Tests\Unit\Assessment\Infrastructure\Sulu\Rest;

use App\Assessment\Domain\Model\Attempt;
use App\Assessment\Domain\Repository\AttemptRepositoryInterface;
use App\Assessment\Infrastructure\Sulu\Rest\AttemptController;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(AttemptController::class)]
final class AttemptControllerTest extends TestCase
{
    private AttemptRepositoryInterface&MockObject $attempts;
    private AttemptController $controller;

    protected function setUp(): void
    {
        $this->attempts = $this->createMock(AttemptRepositoryInterface::class);
        $this->controller = new AttemptController($this->attempts);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testCgetActionReturnsDefaultPaginatedAttempts(): void
    {
        $attemptOne = new Attempt('exercise-1', 'session-1', [[1]], 1, 1);
        $attemptTwo = new Attempt('exercise-2', 'session-2', [[0]], 0, 1);

        $this->attempts->method('findPaginated')->with(1, 10)->willReturn([$attemptOne, $attemptTwo]);
        $this->attempts->method('count')->willReturn(2);

        $response = $this->controller->cgetAction($this->queryRequest([]));
        $body = $this->decode($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(1, $body['page']);
        self::assertSame(10, $body['limit']);
        self::assertSame(2, $body['total']);
        self::assertSame(1, $body['pages']);

        /** @var array<string, mixed> $embedded */
        $embedded = $body['_embedded'];
        /** @var list<array{exerciseUuid: string, sessionId: string}> $items */
        $items = $embedded['attempts'];
        self::assertCount(2, $items);
        self::assertSame('exercise-1', $items[0]['exerciseUuid']);
        self::assertSame('session-1', $items[0]['sessionId']);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testCgetActionAppliesExplicitPageAndLimit(): void
    {
        $attempt = new Attempt('exercise-6', 'session-6', [[1]], 1, 1);

        $this->attempts->method('findPaginated')->with(2, 5)->willReturn([$attempt]);
        $this->attempts->method('count')->willReturn(12);

        $response = $this->controller->cgetAction($this->queryRequest(['page' => '2', 'limit' => '5']));
        $body = $this->decode($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(2, $body['page']);
        self::assertSame(5, $body['limit']);
        self::assertSame(12, $body['total']);
        self::assertSame(3, $body['pages']);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testCgetActionDefaultsGracefullyOnMalformedPageAndLimit(): void
    {
        $this->attempts->method('findPaginated')->with(1, 10)->willReturn([]);
        $this->attempts->method('count')->willReturn(0);

        $response = $this->controller->cgetAction($this->queryRequest(['page' => 'not-a-number', 'limit' => '-5']));
        $body = $this->decode($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(1, $body['page']);
        self::assertSame(10, $body['limit']);
    }

    public function testDeleteActionRemovesAttempt(): void
    {
        $attempt = new Attempt('exercise-1', 'session-1', [[1]], 1, 1);
        $this->attempts->method('find')->with(1)->willReturn($attempt);
        $this->attempts->expects(self::once())->method('remove')->with($attempt);

        $response = $this->controller->deleteAction(1);

        self::assertSame(204, $response->getStatusCode());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testDeleteActionReturns404ForUnknownAttempt(): void
    {
        $this->attempts->method('find')->willReturn(null);

        $response = $this->controller->deleteAction(999);

        self::assertSame(404, $response->getStatusCode());
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
     * @param array<string, string> $query
     */
    private function queryRequest(array $query): Request
    {
        return new Request($query);
    }
}
