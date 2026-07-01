<?php

declare(strict_types=1);

namespace App\Assessment\Infrastructure;

use App\Assessment\Domain\Model\AnswerKey;
use Doctrine\ORM\EntityManagerInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Page\Domain\Model\PageDimensionContent;

/**
 * Loads the authoritative answer key for an exercise page directly from
 * Sulu's own content storage. Mirrors the raw-Doctrine-query style already
 * used in App\Controller\Website\ArticlesByTaxonomyController instead of
 * pulling in the headless StructureResolver/DocumentManager machinery.
 */
final readonly class ExerciseContentReader
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function findAnswerKey(string $pageUuid): ?AnswerKey
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

        /** @var list<array{correct?: string, explanation?: string|null}> $questions */
        $questions = (array) ($row['templateData']['questions'] ?? []);

        return new AnswerKey(\array_map(
            static fn (array $question): array => [
                'correct' => (string) ($question['correct'] ?? ''),
                'explanation' => $question['explanation'] ?? null,
            ],
            $questions,
        ));
    }
}
