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

    /**
     * @return list<Question>
     */
    public function findAll(): array
    {
        return $this->em->getRepository(Question::class)->findAll();
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
