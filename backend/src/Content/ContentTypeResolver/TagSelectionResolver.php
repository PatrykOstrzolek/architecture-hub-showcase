<?php

declare(strict_types=1);

namespace App\Content\ContentTypeResolver;

use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FieldMetadata;
use Sulu\Bundle\HeadlessBundle\Content\ContentTypeResolver\ContentTypeResolverInterface;
use Sulu\Bundle\HeadlessBundle\Content\ContentView;
use Sulu\Bundle\TagBundle\Tag\TagRepositoryInterface;

readonly class TagSelectionResolver implements ContentTypeResolverInterface
{
    public static function getContentType(): string
    {
        return 'tag_selection';
    }

    public function __construct(private TagRepositoryInterface $tagRepository)
    {
    }

    public function resolve(mixed $data, FieldMetadata $fieldMetadata, string $locale, array $attributes = []): ContentView
    {
        if (empty($data) || !\is_array($data)) {
            return new ContentView([], ['ids' => []]);
        }

        $tags = $this->tagRepository->findBy(['id' => $data]);

        // Preserve the original order from $data (DB returns unordered).
        $byId = [];
        foreach ($tags as $tag) {
            $byId[$tag->getId()] = $tag->getName();
        }

        $names = [];
        foreach ($data as $id) {
            if (!\is_int($id) && !\is_string($id)) {
                continue;
            }
            if (isset($byId[$id])) {
                $names[] = $byId[$id];
            }
        }

        return new ContentView($names, ['ids' => $data]);
    }
}
