<?php

declare(strict_types=1);

namespace App\Assessment\Infrastructure\Doctrine;

use App\Assessment\Domain\Model\Question;
use App\Assessment\Domain\Repository\QuestionRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineQuestionRepository implements QuestionRepositoryInterface
{
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
        if ([] === $ids) {
            return [];
        }

        /** @var list<Question> $found */
        $found = $this->em->getRepository(Question::class)->findBy(['id' => $ids]);
        $byId = [];
        foreach ($found as $question) {
            $id = $question->getId();
            if (null !== $id) {
                $byId[$id] = $question;
            }
        }

        // Preserve the requested order — Sulu's selection picker widgets
        // resolve labels via ?ids=... and expect results in that order.
        return \array_values(\array_filter(\array_map(
            static fn (int $id): ?Question => $byId[$id] ?? null,
            $ids,
        )));
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
