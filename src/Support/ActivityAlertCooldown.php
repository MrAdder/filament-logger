<?php

namespace MrAdder\FilamentLogger\Support;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;
use Spatie\Activitylog\Contracts\Activity as ActivityContract;

/**
 * Throttles repeat alerts for a rule and channel.
 *
 * A claim is atomic: the first caller to claim a window wins, and everything
 * matching the same rule, channel, log name, event, and risk is suppressed
 * until it expires. A failed delivery releases the claim so the next matching
 * activity retries rather than being silently swallowed.
 */
class ActivityAlertCooldown
{
    protected const PREFIX = 'filament-logger:alerts:cooldown:';

    public function __construct(
        protected ActivityRiskResolver $riskResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $rule
     */
    public function applies(array $rule): bool
    {
        return $this->secondsFor($rule) > 0;
    }

    /**
     * @param  array<string, mixed>  $rule
     * @return bool Whether this caller took the window.
     */
    public function claim(string $ruleName, array $rule, string $channel, ActivityContract $activity): bool
    {
        return $this->cache()->add(
            $this->key($ruleName, $rule, $channel, $activity),
            true,
            now()->addSeconds($this->secondsFor($rule)),
        );
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    public function release(string $ruleName, array $rule, string $channel, ActivityContract $activity): void
    {
        $this->cache()->forget($this->key($ruleName, $rule, $channel, $activity));
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    public function secondsFor(array $rule): int
    {
        $minutes = data_get($rule, 'cooldown_minutes');

        // Threshold rules match every activity once the count is reached, so
        // without a cooldown a single spike would alert on each subsequent
        // event. Defaulting to the detection window keeps it to one alert per
        // spike, which is what the exact-count comparison used to provide.
        if ($minutes === null && data_get($rule, 'type') === 'threshold') {
            $minutes = data_get($rule, 'window_minutes', 10);
        }

        return max(0, (int) $minutes) * 60;
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    protected function key(string $ruleName, array $rule, string $channel, ActivityContract $activity): string
    {
        $pattern = [
            'rule' => $ruleName,
            'channel' => $channel,
            'type' => data_get($rule, 'type'),
            'log_name' => data_get($activity, 'log_name'),
            'event' => data_get($activity, 'event'),
            'risk' => $this->riskResolver->resolveForActivity($activity),
        ];

        return static::PREFIX.hash('sha256', (string) json_encode($pattern));
    }

    protected function cache(): CacheRepository
    {
        $store = config('filament-logger.alerts.cache_store');

        return filled($store) ? Cache::store((string) $store) : Cache::store();
    }
}
