<?php

declare(strict_types=1);

namespace App\Assessment\Application\Port;

/**
 * Anti-corruption boundary between Assessment and Sulu: the only way the
 * Application layer learns which QuestionSet an exercise page references.
 * The real implementation touches Sulu's page/dimension-content storage;
 * Assessment's Domain/Application code only ever depends on this interface.
 */
interface ExerciseQuestionSetLocatorInterface
{
    public function findQuestionSetId(string $pageUuid): ?int;
}
