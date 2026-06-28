<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller\Website;

use App\Controller\Website\TaxonomyController;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(TaxonomyController::class)]
final class TaxonomyControllerTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private TaxonomyController $controller;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->controller = new TaxonomyController($this->em);
    }

    #[DataProvider('shortQueryProvider')]
    public function testShortQueryReturnsEmptyWithoutHittingDatabase(string $q): void
    {
        $this->em->expects(self::never())->method('createQueryBuilder');

        $response = $this->controller->getAction(new Request(['q' => $q]));
        $body = \json_decode((string) $response->getContent(), true);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['categories' => [], 'tags' => []], $body);
    }

    /** @return array<string, array{string}> */
    public static function shortQueryProvider(): array
    {
        return [
            'empty string' => [''],
            'one char' => ['a'],
            'whitespace' => [' '],
        ];
    }
}
