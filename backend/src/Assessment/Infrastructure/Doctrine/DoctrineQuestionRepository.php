<?php

declare(strict_types=1);

namespace App\Assessment\Infrastructure\Doctrine;

use App\Assessment\Domain\Model\Question;
use App\Assessment\Domain\Repository\QuestionRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineQuestionRepository implements QuestionRepositoryInterface
{
    use PreservesRequestedIdOrder;

    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function find(int $id): ?Question
    {
        return $this->em->find(Question::class, $id);
    }

    public function findWithOptions(int $id): ?Question
    {
        $result = $this->em->createQueryBuilder()
            ->select('q', 'options')
            ->from(Question::class, 'q')
            ->leftJoin('q.options', 'options')
            ->where('q.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();

        return $result instanceof Question ? $result : null;
    }

    /**
     * @param list<int> $ids
     *
     * @return list<Question>
     */
    public function findByIds(array $ids): array
    {
        return $this->findByIdsPreservingOrder($this->em, Question::class, $ids);
    }

    /**
     * @return list<Question>
     */
    public function findPaginated(int $page, int $limit): array
    {
        /** @var list<Question> $result */
        $result = $this->em->createQueryBuilder()
            ->select('q')
            ->from(Question::class, 'q')
            ->orderBy('q.id', 'DESC')
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
            ->select('COUNT(q.id)')
            ->from(Question::class, 'q')
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $total;
    }

    public function save(Question $question): void
    {
        $this->em->persist($question);
        $this->em->flush();
    }

    public function remove(Question $question): void
    {
        $this->em->remove($question);
        $this->em->flush();
    }
}
