<?php

declare(strict_types=1);

namespace App\Assessment\Domain\Exception;

final class ExerciseNotFoundException extends \RuntimeException
{
    public function __construct(string $exerciseUuid)
    {
        parent::__construct(\sprintf('No live exercise page found for UUID "%s".', $exerciseUuid));
    }
}
