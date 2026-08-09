<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Dependency-free spam protection for public fill pages.
 *
 * Decides per submission whether to hard-reject a bot (honeypot, time trap)
 * or quietly flag a suspicious-but-possibly-legit submission (IP velocity).
 * The service is deliberately stateless apart from the velocity counter, so
 * it is easy to unit test and safe to swap for reCAPTCHA/hCaptcha later.
 */
class SpamProtectionService
{
    protected const VELOCITY_KEY = 'spam:velocity:';

    /**
     * @param  array{honeypot?: mixed, loaded_at?: mixed}  $signals
     * @return array{blocked: bool, flagged: bool, reasons: array<int, string>}
     */
    public function decide(string $ip, array $signals = []): array
    {
        if (! $this->enabled()) {
            return $this->cleanDecision();
        }

        $blocked = false;
        $reasons = [];

        $honeypot = trim((string) ($signals['honeypot'] ?? ''));
        if ($honeypot !== '') {
            $blocked = true;
            $reasons[] = 'honeypot';
        }

        $loadedAt = $signals['loaded_at'] ?? null;
        if (! $blocked && $this->isTimestamp($loadedAt)) {
            $elapsed = time() - (int) $loadedAt;
            if ($elapsed < config('spam.min_fill_seconds')) {
                $blocked = true;
                $reasons[] = 'filled_too_fast';
            }
        }

        return [
            'blocked' => $blocked,
            'flagged' => $blocked ? false : $this->isOverVelocity($ip, $reasons),
            'reasons' => $reasons,
        ];
    }

    /**
     * Count a submit attempt for the IP velocity window. Call this on every
     * submit attempt so repeat offenders are flagged after the threshold.
     */
    public function registerAttempt(string $ip): void
    {
        if (! $this->enabled() || ! (bool) config('spam.velocity.enabled')) {
            return;
        }

        $key = self::VELOCITY_KEY.$ip;
        $window = (int) config('spam.velocity.window_seconds');

        Cache::put($key, $this->attemptCount($ip) + 1, now()->addSeconds($window));
    }

    public function resetAttempts(string $ip): void
    {
        Cache::forget(self::VELOCITY_KEY.$ip);
    }

    protected function isOverVelocity(string $ip, array &$reasons): bool
    {
        if (! (bool) config('spam.velocity.enabled')) {
            return false;
        }

        if ($this->attemptCount($ip) >= (int) config('spam.velocity.max_attempts')) {
            $reasons[] = 'velocity';

            return true;
        }

        return false;
    }

    protected function attemptCount(string $ip): int
    {
        return (int) Cache::get(self::VELOCITY_KEY.$ip, 0);
    }

    protected function enabled(): bool
    {
        return (bool) config('spam.enabled', true);
    }

    protected function isTimestamp(mixed $value): bool
    {
        return is_numeric($value) && (int) $value > 0;
    }

    protected function cleanDecision(): array
    {
        return ['blocked' => false, 'flagged' => false, 'reasons' => []];
    }
}
