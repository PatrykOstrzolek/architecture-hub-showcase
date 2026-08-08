<?php

declare(strict_types=1);

namespace App\Assessment\Infrastructure\Sulu\Rest;

use Symfony\Component\HttpFoundation\Request;

/**
 * Shared by every admin list endpoint's listAction(): Sulu's ListRestHelper
 * (unlike this codebase's former BuildsPaginatedRepresentation) passes the
 * raw `page`/`limit` query values straight through to DoctrineListBuilder
 * with no validation — a non-numeric or non-positive value 500s deep inside
 * DoctrineListBuilder::findIdsByGivenCriteria() ("Unsupported operand types:
 * string - int"), confirmed by hand against a running admin. This rewrites
 * malformed values on the request in place, before RestHelper reads them,
 * defaulting to page=1 / the given default limit — matching this codebase's
 * existing "never throws on bad input" convention (SubmitAttemptService,
 * QuestionSetController::applyQuestions()).
 */
final class ListPageAndLimitSanitizer
{
    public static function sanitize(Request $request, int $defaultLimit = 10): void
    {
        self::sanitizeParam($request, 'page', 1);
        self::sanitizeParam($request, 'limit', $defaultLimit);
    }

    private static function sanitizeParam(Request $request, string $name, int $default): void
    {
        $value = $request->query->get($name);
        if (null === $value) {
            return;
        }

        if (!\ctype_digit((string) $value) || (int) $value < 1) {
            $request->query->set($name, (string) $default);
        }
    }
}
