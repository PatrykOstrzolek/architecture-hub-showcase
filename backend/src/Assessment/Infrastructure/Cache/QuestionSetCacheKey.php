<?php

declare(strict_types=1);

namespace App\Assessment\Infrastructure\Cache;

/**
 * The single, shared cache-key format for a QuestionSet's headless-resolved
 * representation (`cache.app`, via Symfony\Contracts\Cache\CacheInterface).
 * Consulted from three call sites — QuestionSetSelectionPropertyResolver
 * (read/populate) and QuestionSetController/QuestionController (invalidate
 * on save/remove) — all of which must agree on the exact key for a given id.
 * A single static method is the smallest form that guarantees they can never
 * drift, without introducing a general-purpose caching abstraction (see
 * ADR-0012/0014, Requirement 5).
 */
final class QuestionSetCacheKey
{
    private function __construct()
    {
    }

    public static function for(int $id): string
    {
        return 'assessment_question_set_' . $id . '_headless';
    }
}
