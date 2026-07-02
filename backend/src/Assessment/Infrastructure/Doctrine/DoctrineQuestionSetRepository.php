<?php

declare(strict_types=1);

namespace App\Assessment\Infrastructure\Doctrine;

use App\Assessment\Domain\Model\QuestionSet;
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
     * @return list<QuestionSet>
     */
    public function findAll(): array
    {
        return $this->em->getRepository(QuestionSet::class)->findAll();
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
