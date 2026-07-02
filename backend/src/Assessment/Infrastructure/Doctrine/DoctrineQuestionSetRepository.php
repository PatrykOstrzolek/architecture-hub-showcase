<?php

declare(strict_types=1);

namespace App\Assessment\Infrastructure\Doctrine;

use App\Assessment\Domain\Model\QuestionSet;
use App\Assessment\Domain\Repository\QuestionSetRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineQuestionSetRepository implements QuestionSetRepositoryInterface
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function find(int $id): ?QuestionSet
    {
        return $this->em->find(QuestionSet::class, $id);
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
        if ([] === $ids) {
            return [];
        }

        /** @var list<QuestionSet> $found */
        $found = $this->em->getRepository(QuestionSet::class)->findBy(['id' => $ids]);
        $byId = [];
        foreach ($found as $questionSet) {
            $id = $questionSet->getId();
            if (null !== $id) {
                $byId[$id] = $questionSet;
            }
        }

        return \array_values(\array_filter(\array_map(
            static fn (int $id): ?QuestionSet => $byId[$id] ?? null,
            $ids,
        )));
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
