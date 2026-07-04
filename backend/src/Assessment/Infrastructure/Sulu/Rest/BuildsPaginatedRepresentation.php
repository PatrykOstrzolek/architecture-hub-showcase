<?php

declare(strict_types=1);

namespace App\Assessment\Infrastructure\Sulu\Rest;

use Sulu\Component\Rest\ListBuilder\ListRestHelper;
use Sulu\Component\Rest\ListBuilder\PaginatedRepresentation;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Shared "read page/limit from the request, fetch a bounded page, wrap in
 * PaginatedRepresentation" logic for list endpoints backed by a paginated
 * repository query. Introduced for AttemptController; reused unchanged by
 * QuestionController/QuestionSetController once they gain the identical
 * pagination pattern.
 *
 * Only page/limit are read via Sulu's ListRestHelper — search activation
 * (backed by searchability="yes" XML config) is explicitly out of scope
 * this round (see spec Key Decisions/Out of Scope).
 *
 * page/limit parsing is tolerant of missing or malformed values (never
 * throws), matching this domain's existing "never throws on bad input"
 * convention (SubmitAttemptService, QuestionSetController::applyQuestions()):
 * a missing/invalid page defaults to 1, a missing/invalid limit defaults to
 * self::DEFAULT_LIMIT.
 */
trait BuildsPaginatedRepresentation
{
    private const DEFAULT_LIMIT = 10;

    /**
     * @param callable(int $page, int $limit): list<mixed> $fetchPage returns the bounded, ordered page of items
     * @param callable(): int $countAll returns the total unfiltered count
     */
    private function buildPaginatedRepresentation(
        Request $request,
        string $rel,
        callable $fetchPage,
        callable $countAll,
    ): PaginatedRepresentation {
        $requestStack = new RequestStack();
        $requestStack->push($request);
        $listRestHelper = new ListRestHelper($requestStack);

        $page = $this->toPositiveInt($listRestHelper->getPage(), 1);
        $limit = $this->toPositiveInt($listRestHelper->getLimit(), self::DEFAULT_LIMIT);

        $items = $fetchPage($page, $limit);
        $total = $countAll();

        return new PaginatedRepresentation($items, $rel, $page, $limit, $total);
    }

    private function toPositiveInt(mixed $value, int $default): int
    {
        if (!\is_numeric($value)) {
            return $default;
        }

        $intValue = (int) $value;

        return $intValue > 0 ? $intValue : $default;
    }
}
