<?php

declare(strict_types=1);

namespace App\Assessment\Domain\Repository;

use App\Assessment\Domain\Model\Attempt;

interface AttemptRepositoryInterface
{
    public function save(Attempt $attempt): void;
}
