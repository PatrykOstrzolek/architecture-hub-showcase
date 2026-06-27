<?php

declare(strict_types=1);

namespace App\Controller\Website;

use Doctrine\ORM\EntityManagerInterface;
use Sulu\Article\Domain\Model\ArticleDimensionContent;
use Sulu\Bundle\CategoryBundle\Entity\Category;
use Sulu\Bundle\TagBundle\Entity\Tag;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

readonly class ArticlesByTaxonomyController
{
    public function __construct(private EntityManagerInterface $em) {}

    #[Route('/api/articles', name: 'app.api.articles', methods: ['GET'])]
    public function getAction(Request $request): JsonResponse
    {
        $categoryKey = trim($request->query->getString('category'));
        $tagName = trim($request->query->getString('tag'));

        if ('' === $categoryKey && '' === $tagName) {
            return new JsonResponse(['_embedded' => ['hits' => []]]);
        }

        $filterIds = $categoryKey !== ''
            ? $this->resolveCategoryIds($categoryKey)
            : $this->resolveTagIds($tagName);

        if ([] === $filterIds) {
            return new JsonResponse(['_embedded' => ['hits' => []]]);
        }

        $rows = $this->em->createQueryBuilder()
            ->select('dc.title', 'route.slug AS url', 'dc.authored', 'dc.templateData')
            ->from(ArticleDimensionContent::class, 'dc')
            ->leftJoin('dc.route', 'route')
            ->where('dc.stage = :stage')
            ->andWhere('dc.locale IS NOT NULL')
            ->andWhere('dc.version = :version')
            ->setParameter('stage', DimensionContentInterface::STAGE_LIVE)
            ->setParameter('version', DimensionContentInterface::CURRENT_VERSION)
            ->getQuery()
            ->getArrayResult();

        $field = $categoryKey !== '' ? 'categories' : 'tags';

        $hits = [];
        foreach ($rows as $row) {
            $ids = $row['templateData'][$field] ?? [];
            if (!array_intersect($filterIds, (array) $ids)) {
                continue;
            }
            $hits[] = [
                'title' => $row['title'],
                'url' => $row['url'],
                'content' => isset($row['templateData']['summary']) ? [$row['templateData']['summary']] : [],
                'authoredAt' => $row['authored']?->format('c'),
            ];
        }

        return new JsonResponse(['_embedded' => ['hits' => $hits]]);
    }

    /** @return int[] */
    private function resolveCategoryIds(string $key): array
    {
        $result = $this->em->createQueryBuilder()
            ->select('c.id')
            ->from(Category::class, 'c')
            ->where('c.key = :key')
            ->setParameter('key', $key)
            ->getQuery()
            ->getOneOrNullResult();

        return $result ? [(int) $result['id']] : [];
    }

    /** @return int[] */
    private function resolveTagIds(string $name): array
    {
        $result = $this->em->createQueryBuilder()
            ->select('t.id')
            ->from(Tag::class, 't')
            ->where('t.name = :name')
            ->setParameter('name', $name)
            ->getQuery()
            ->getOneOrNullResult();

        return $result ? [(int) $result['id']] : [];
    }
}
