<?php

declare(strict_types=1);

namespace App\Assessment\Infrastructure\Sulu;

use App\Assessment\Application\Port\ExerciseQuestionSetLocatorInterface;
use Doctrine\ORM\EntityManagerInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Page\Domain\Model\PageDimensionContent;

/**
 * The one adapter allowed to touch Sulu's page/dimension-content storage —
 * the Anti-Corruption Layer boundary between Assessment and Sulu. Mirrors
 * the raw-Doctrine-query style already used in
 * App\Controller\Website\ArticlesByTaxonomyController instead of pulling in
 * the headless StructureResolver/DocumentManager machinery.
 */
final readonly class SuluExerciseQuestionSetLocator implements ExerciseQuestionSetLocatorInterface
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function findQuestionSetId(string $pageUuid): ?int
    {
        /** @var array{templateData: array<string, mixed>}|null $row */
        $row = $this->em->createQueryBuilder()
            ->select('dc.templateData')
            ->from(PageDimensionContent::class, 'dc')
            ->innerJoin('dc.page', 'page')
            ->where('page.uuid = :uuid')
            ->andWhere('dc.templateKey = :template')
            ->andWhere('dc.stage = :stage')
            ->andWhere('dc.locale IS NOT NULL')
            ->andWhere('dc.version = :version')
            ->setParameter('uuid', $pageUuid)
            ->setParameter('template', 'exercise')
            ->setParameter('stage', DimensionContentInterface::STAGE_LIVE)
            ->setParameter('version', DimensionContentInterface::CURRENT_VERSION)
            ->getQuery()
            ->getOneOrNullResult();

        if (null === $row) {
            return null;
        }

        $questionSetId = $row['templateData']['questionSet'] ?? null;

        return \is_int($questionSetId) ? $questionSetId : null;
    }
}
