<?php

declare(strict_types=1);

namespace App\Assessment\Domain\Model;

/**
 * Implemented by every Assessment entity with an autoincrement id, so
 * Infrastructure\Doctrine\PreservesRequestedIdOrder can be shared across
 * repositories without each one re-deriving the same order-preserving
 * id-lookup logic.
 */
interface Identifiable
{
    public function getId(): ?int;
}
