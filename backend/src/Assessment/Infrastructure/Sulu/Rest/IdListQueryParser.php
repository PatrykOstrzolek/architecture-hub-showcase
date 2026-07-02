<?php

declare(strict_types=1);

namespace App\Assessment\Infrastructure\Sulu\Rest;

/**
 * Shared by every admin list endpoint's cgetAction(): Sulu's generic
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
}
