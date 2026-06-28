<?php

declare(strict_types=1);

namespace App\Controller\Website;

use Doctrine\ORM\EntityManagerInterface;
use Sulu\Bundle\CategoryBundle\Entity\Category;
use Sulu\Bundle\TagBundle\Entity\Tag;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

readonly class TaxonomyController
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    #[Route('/api/taxonomy', name: 'app.api.taxonomy', methods: ['GET'])]
    public function getAction(Request $request): JsonResponse
    {
        $q = \trim($request->query->getString('q'));

        if (\strlen($q) < 2) {
            return new JsonResponse(['categories' => [], 'tags' => []]);
        }

        $pattern = '%' . \mb_strtolower($q) . '%';

        $categories = $this->em->createQueryBuilder()
            ->select('c.id', 'c.key', 't.translation AS name')
            ->from(Category::class, 'c')
            ->join('c.translations', 't')
            ->where('LOWER(t.translation) LIKE :q')
            ->setParameter('q', $pattern)
            ->setMaxResults(5)
            ->getQuery()
            ->getArrayResult();

        $tagRows = $this->em->createQueryBuilder()
            ->select('t.id', 't.name')
            ->from(Tag::class, 't')
            ->where('LOWER(t.name) LIKE :q')
            ->setParameter('q', $pattern)
            ->setMaxResults(5)
            ->getQuery()
            ->getArrayResult();

        return new JsonResponse([
            'categories' => $categories,
            'tags' => $tagRows,
        ]);
    }
}
