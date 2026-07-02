<?php

declare(strict_types=1);

namespace App\Assessment\Infrastructure\Doctrine;

use App\Assessment\Domain\Model\Identifiable;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Shared by every Doctrine repository's findByIds(): batches into a single
 * `WHERE id IN (...)` query, then reorders the result to match the
 * requested id order — Sulu's selection picker widgets resolve labels for
 * already-selected ids via `?ids=1,2,3` and expect results back in that
 * order (see ADR-0014).
 */
trait PreservesRequestedIdOrder
{
    /**
     * @template T of Identifiable
     *
     * @param class-string<T> $entityClass
     * @param list<int> $ids
     *
     * @return list<T>
     */
    private function findByIdsPreservingOrder(EntityManagerInterface $em, string $entityClass, array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        /** @var list<T> $found */
        $found = $em->getRepository($entityClass)->findBy(['id' => $ids]);

        $byId = [];
        foreach ($found as $entity) {
            $id = $entity->getId();
            if (null !== $id) {
                $byId[$id] = $entity;
            }
        }

        return \array_values(\array_filter(\array_map(
            static fn (int $id) => $byId[$id] ?? null,
            $ids,
        )));
    }
}
