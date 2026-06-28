<?php

declare(strict_types=1);

namespace App\Tests\Unit\Content\ContentTypeResolver;

use App\Content\ContentTypeResolver\TagSelectionResolver;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FieldMetadata;
use Sulu\Bundle\TagBundle\Entity\Tag;
use Sulu\Bundle\TagBundle\Tag\TagRepositoryInterface;

#[CoversClass(TagSelectionResolver::class)]
final class TagSelectionResolverTest extends TestCase
{
    private TagRepositoryInterface&MockObject $tagRepository;
    private TagSelectionResolver $resolver;

    protected function setUp(): void
    {
        $this->tagRepository = $this->createMock(TagRepositoryInterface::class);
        $this->resolver = new TagSelectionResolver($this->tagRepository);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testGetContentType(): void
    {
        self::assertSame('tag_selection', TagSelectionResolver::getContentType());
    }

    #[DataProvider('emptyDataProvider')]
    public function testEmptyDataReturnsEmptyView(mixed $data): void
    {
        $this->tagRepository->expects(self::never())->method('findBy');

        $view = $this->resolver->resolve($data, $this->fieldMetadata(), 'en');

        self::assertSame([], $view->getContent());
        self::assertSame(['ids' => []], $view->getView());
    }

    /** @return array<string, array{mixed}> */
    public static function emptyDataProvider(): array
    {
        return [
            'null' => [null],
            'empty array' => [[]],
            'string' => ['not-an-array'],
            'integer' => [42],
        ];
    }

    public function testSingleTagResolvedByName(): void
    {
        $tag = $this->makeTag(1, 'php');
        $this->tagRepository->method('findBy')->with(['id' => [1]])->willReturn([$tag]);

        $view = $this->resolver->resolve([1], $this->fieldMetadata(), 'en');

        self::assertSame(['php'], $view->getContent());
        self::assertSame(['ids' => [1]], $view->getView());
    }

    public function testMultipleTagsPreserveInputOrder(): void
    {
        $tagA = $this->makeTag(10, 'alpha');
        $tagB = $this->makeTag(20, 'beta');

        // Repository returns them in reverse order — resolver must restore input order.
        $this->tagRepository->method('findBy')->with(['id' => [20, 10]])->willReturn([$tagA, $tagB]);

        $view = $this->resolver->resolve([20, 10], $this->fieldMetadata(), 'en');

        self::assertSame(['beta', 'alpha'], $view->getContent());
    }

    public function testMissingTagsAreSkipped(): void
    {
        $tagA = $this->makeTag(1, 'exists');
        $this->tagRepository->method('findBy')->with(['id' => [1, 999]])->willReturn([$tagA]);

        $view = $this->resolver->resolve([1, 999], $this->fieldMetadata(), 'en');

        self::assertSame(['exists'], $view->getContent());
        self::assertSame(['ids' => [1, 999]], $view->getView());
    }

    private function makeTag(int $id, string $name): Tag
    {
        $tag = $this->createStub(Tag::class);
        $tag->method('getId')->willReturn($id);
        $tag->method('getName')->willReturn($name);

        return $tag;
    }

    private function fieldMetadata(): FieldMetadata
    {
        return $this->createStub(FieldMetadata::class);
    }
}
