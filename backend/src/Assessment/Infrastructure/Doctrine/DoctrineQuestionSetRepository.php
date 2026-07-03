<?php

declare(strict_types=1);

namespace App\Assessment\Infrastructure\Doctrine;

use App\Assessment\Domain\Model\QuestionSet;
use App\Assessment\Domain\Model\QuestionSetItem;
use App\Assessment\Domain\Repository\QuestionSetRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineQuestionSetRepository implements QuestionSetRepositoryInterface
{
    use PreservesRequestedIdOrder;

    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function find(int $id): ?QuestionSet
    {
        return $this->em->find(QuestionSet::class, $id);
    }

    public function findWithQuestions(int $id): ?QuestionSet
    {
        $result = $this->em->createQueryBuilder()
            ->select('qs', 'items', 'question', 'options')
            ->from(QuestionSet::class, 'qs')
            ->leftJoin('qs.items', 'items')
            ->leftJoin('items.question', 'question')
            ->leftJoin('question.options', 'options')
            ->where('qs.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();

        return $result instanceof QuestionSet ? $result : null;
    }

    /**
     * @param list<int> $ids
     *
     * @return list<QuestionSet>
     */
    public function findByIds(array $ids): array
    {
        return $this->findByIdsPreservingOrder($this->em, QuestionSet::class, $ids);
    }

    /**
     * @return list<QuestionSet>
     */
    public function findPaginated(int $page, int $limit): array
    {
        /** @var list<QuestionSet> $result */
        $result = $this->em->createQueryBuilder()
            ->select('qs')
            ->from(QuestionSet::class, 'qs')
            ->orderBy('qs.id', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $result;
    }

    public function count(): int
    {
        /** @var int|string $total */
        $total = $this->em->createQueryBuilder()
            ->select('COUNT(qs.id)')
            ->from(QuestionSet::class, 'qs')
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $total;
    }

    /**
     * @return list<int>
     */
    public function findQuestionSetIdsContaining(int $questionId): array
    {
        /** @var list<int> $result */
        $result = $this->em->createQueryBuilder()
            ->select('DISTINCT IDENTITY(item.questionSet)')
            ->from(QuestionSetItem::class, 'item')
            ->where('item.question = :questionId')
            ->setParameter('questionId', $questionId)
            ->getQuery()
            ->getSingleColumnResult();

        return \array_map(static fn (int|string $id): int => (int) $id, $result);
    }

    public function save(QuestionSet $questionSet): void
    {
        $this->em->persist($questionSet);
        $this->em->flush();
    }

    public function remove(QuestionSet $questionSet): void
    {
        $this->em->remove($questionSet);
        $this->em->flush();
    }
}
