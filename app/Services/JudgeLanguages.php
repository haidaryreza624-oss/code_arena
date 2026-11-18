<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Thin cache wrapper around JudgeClient::languages().
 * The submission form dropdown reads from available() — this keeps the
 * form fast and avoids hammering the judge for its language list.
 *
 * Falls back to an empty list if the judge is unreachable so the form
 * still renders (with a friendly error surfaced separately).
 */
final class JudgeLanguages
{
    private const CACHE_KEY = 'judge:languages';
    private const TTL_SECONDS = 300; // 5 minutes

    public function __construct(private readonly JudgeClient $judge)
    {
    }

    /**
     * Full language list as returned by the judge, cached.
     *
     * @return array<int, array{id: int, name: string, available: bool, needs_compilation: bool}>
     */
    public function all(): array
    {
        return Cache::remember(self::CACHE_KEY, self::TTL_SECONDS, function () {
            try {
                return $this->judge->languages();
            } catch (\Throwable $e) {
                report($e);
                return [];
            }
        });
    }

    /**
     * Only languages whose required compiler/runtime is detected on the judge host.
     *
     * @return array<int, array{id: int, name: string, available: bool, needs_compilation: bool}>
     */
    public function available(): array
    {
        return array_values(array_filter(
            $this->all(),
            fn (array $l) => ($l['available'] ?? false) === true,
        ));
    }

    /**
     * Just the language names, lowercased. Used for validation against
     * what the contestant submitted.
     *
     * @return array<int, string>
     */
    public function availableNames(): array
    {
        return array_map(
            fn (array $l) => strtolower($l['name']),
            $this->available(),
        );
    }

    public function supports(string $language): bool
    {
        return in_array(strtolower(trim($language)), $this->availableNames(), true);
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
