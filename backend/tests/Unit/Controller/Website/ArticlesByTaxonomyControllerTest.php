<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller\Website;

use App\Controller\Website\ArticlesByTaxonomyController;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(ArticlesByTaxonomyController::class)]
final class ArticlesByTaxonomyControllerTest extends TestCase
{
    private EntityManagerInterface&Stub $em;
    private ArticlesByTaxonomyController $controller;

    protected function setUp(): void
    {
        $this->em = $this->createStub(EntityManagerInterface::class);
        $this->controller = new ArticlesByTaxonomyController($this->em);
    }

    public function testUnknownCategoryKeyReturnsEmptyHits(): void
    {
        $this->em->method('createQueryBuilder')->willReturn($this->stubQb());

        $response = $this->controller->getAction(new Request(['category' => 'does-not-exist']));
        $body = \json_decode((string) $response->getContent(), true);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['_embedded' => ['hits' => []]], $body);
    }

    public function testUnknownTagNameReturnsEmptyHits(): void
    {
        $this->em->method('createQueryBuilder')->willReturn($this->stubQb());

        $response = $this->controller->getAction(new Request(['tag' => 'no-such-tag']));
        $body = \json_decode((string) $response->getContent(), true);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['_embedded' => ['hits' => []]], $body);
    }

    public function testPaginatedListResponseShape(): void
    {
        $this->em->method('createQueryBuilder')->willReturn($this->stubQb());

        $response = $this->controller->getAction(new Request());
        /** @var array{_embedded: array{hits: list<mixed>}, total: mixed, page: mixed, limit: mixed, pages: mixed} $body */
        $body = \json_decode((string) $response->getContent(), true);

        self::assertSame(200, $response->getStatusCode());
        self::assertArrayHasKey('_embedded', $body);
        self::assertArrayHasKey('hits', $body['_embedded']);
        self::assertArrayHasKey('total', $body);
        self::assertArrayHasKey('page', $body);
        self::assertArrayHasKey('limit', $body);
        self::assertArrayHasKey('pages', $body);
    }

    public function testPageClampedToOne(): void
    {
        $this->em->method('createQueryBuilder')->willReturn($this->stubQb());

        $response = $this->controller->getAction(new Request(['page' => '-5']));
        /** @var array{page: mixed} $body */
        $body = \json_decode((string) $response->getContent(), true);

        self::assertSame(1, $body['page']);
    }

    public function testLimitClampedToFifty(): void
    {
        $this->em->method('createQueryBuilder')->willReturn($this->stubQb());

        $response = $this->controller->getAction(new Request(['limit' => '999']));
        /** @var array{limit: mixed} $body */
        $body = \json_decode((string) $response->getContent(), true);

        self::assertSame(50, $body['limit']);
    }

    public function testPagesCalculation(): void
    {
        $this->em->method('createQueryBuilder')->willReturn($this->stubQb(total: 13));

        $response = $this->controller->getAction(new Request(['limit' => '6']));
        /** @var array{total: mixed, pages: mixed} $body */
        $body = \json_decode((string) $response->getContent(), true);

        self::assertSame(13, $body['total']);
        self::assertSame(3, $body['pages']); // ceil(13/6) = 3
    }

    /**
     * Creates a fully-stubbed QueryBuilder mock where every fluent method
     * returns self. `getOneOrNullResult` always returns null (no taxonomy match);
     * `getSingleScalarResult` returns $total; `getArrayResult` returns [].
     */
    private function stubQb(int $total = 0): QueryBuilder&Stub
    {
        $query = $this->createStub(Query::class);
        $query->method('getOneOrNullResult')->willReturn(null);
        $query->method('getSingleScalarResult')->willReturn($total);
        $query->method('getArrayResult')->willReturn([]);

        $qb = $this->createStub(QueryBuilder::class);
        foreach (['select', 'from', 'where', 'andWhere', 'leftJoin', 'orderBy', 'setParameter', 'setMaxResults', 'setFirstResult'] as $method) {
            $qb->method($method)->willReturnSelf();
        }
        $qb->method('getQuery')->willReturn($query);

        return $qb;
    }
}
