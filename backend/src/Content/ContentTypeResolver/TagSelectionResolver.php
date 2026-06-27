<?php

declare(strict_types=1);

namespace App\Content\ContentTypeResolver;

use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FieldMetadata;
use Sulu\Bundle\HeadlessBundle\Content\ContentTypeResolver\ContentTypeResolverInterface;
use Sulu\Bundle\HeadlessBundle\Content\ContentView;
use Sulu\Bundle\TagBundle\Tag\TagManagerInterface;

readonly class TagSelectionResolver implements ContentTypeResolverInterface
{
    public static function getContentType(): string
    {
        return 'tag_selection';
    }

    public function __construct(private TagManagerInterface $tagManager) {}

    public function resolve(mixed $data, FieldMetadata $fieldMetadata, string $locale, array $attributes = []): ContentView
    {
        if (empty($data) || !is_array($data)) {
            return new ContentView([], ['ids' => []]);
        }

        $names = [];
        foreach ($data as $id) {
            $tag = $this->tagManager->findById($id);
            if (null !== $tag) {
                $names[] = $tag->getName();
            }
        }

        return new ContentView($names, ['ids' => $data]);
    }
}
