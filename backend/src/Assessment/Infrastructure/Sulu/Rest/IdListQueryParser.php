<?php

declare(strict_types=1);

namespace App\Assessment\Infrastructure\Sulu\Rest;

use Symfony\Component\HttpFoundation\Request;

/**
 * Shared by every admin list endpoint's listAction(): Sulu's generic
 * selection picker widgets resolve labels for already-selected ids via
 * `?ids=1,2,3` (see ADR-0014). Non-digit tokens (including the empty
 * string from an empty query param) are silently dropped.
 */
final class IdListQueryParser
{
    /**
     * @return list<int>
     */
    public static function parse(string $rawIds): array
    {
        return \array_values(\array_filter(\array_map(
            static fn (string $id): ?int => \ctype_digit($id) ? (int) $id : null,
            \explode(',', $rawIds),
        )));
    }

    /**
     * Parses the request's `ids` param AND rewrites it in place to the
     * sanitized, comma-joined digit-only value (or removes it entirely if
     * nothing valid remains). Without this, Sulu's own ListRestHelper::getIds()
     * — used internally by RestHelper::initializeListBuilder() — parses the
     * *raw* query param independently, with only empty-string filtering (no
     * digit validation). A fully non-numeric `ids` value (e.g. "abc") would
     * then reach DoctrineListBuilder's `WHERE id IN (...)` against an integer
     * column and 500 with a Postgres type error, confirmed by hand against a
     * running admin — the exact class of bug ListPageAndLimitSanitizer exists
     * to prevent for page/limit. Call this BEFORE initializeListBuilder() so
     * both parsers see the same, already-clean value.
     *
     * @return list<int>
     */
    public static function parseAndSanitizeRequest(Request $request): array
    {
        $ids = self::parse($request->query->get('ids', ''));

        if ([] !== $ids) {
            $request->query->set('ids', \implode(',', $ids));
        } else {
            $request->query->remove('ids');
        }

        return $ids;
    }
}
