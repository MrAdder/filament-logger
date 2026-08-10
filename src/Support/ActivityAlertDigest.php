<?php

namespace MrAdder\FilamentLogger\Support;

use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Spatie\Activitylog\ActivitylogServiceProvider;
use Spatie\Activitylog\Contracts\Activity as ActivityContract;

/**
 * Buffers matching activities for rules configured as digests, then releases
 * them as a single alert once the window closes.
 *
 * Buckets are tracked in an explicit index rather than cache tags, because tag
 * scanning is not supported by every cache store.
 */
class ActivityAlertDigest
{
    protected const PREFIX = 'filament-logger:alerts:digest:';

    protected const INDEX_KEY = self::PREFIX.'index';

    /**
     * Buckets are kept a while past their window so a late flush still finds
     * them; the index entry is what drives collection.
     */
    protected const BUCKET_GRACE_MINUTES = 60;

    /**
     * Add an activity to its rule/channel bucket, opening the window if needed.
     *
     * @param  array<string, mixed>  $rule
     */
    public function add(string $ruleName, array $rule, string $channel, ActivityContract $activity): void
    {
        $key = $this->bucketKey($ruleName, $channel);
        $bucket = $this->cache()->get($key);

        if (! is_array($bucket)) {
            $bucket = [
                'rule' => $ruleName,
                'channel' => $channel,
                'opened_at' => now()->toIso8601String(),
                'ids' => [],
                'count' => 0,
            ];
        }

        $bucket['count']++;

        $id = $activity instanceof Model ? $activity->getKey() : data_get($activity, 'id');

        // Keep a bounded sample of ids; the count is what the alert reports.
        if ($id !== null && count($bucket['ids']) < $this->sampleSize()) {
            $bucket['ids'][] = $id;
        }

        $this->cache()->put($key, $bucket, $this->bucketTtl($rule));
        $this->rememberKey($key);
    }

    /**
     * Release every bucket whose window has closed.
     *
     * @param  callable(string, array<string, mixed>, string, ActivityContract, int): void  $sender
     * @return int Number of digests released.
     */
    public function flushDue(callable $sender, bool $force = false): int
    {
        $released = 0;

        foreach ($this->indexedKeys() as $key) {
            $bucket = $this->cache()->get($key);

            if (! is_array($bucket)) {
                $this->forgetKey($key);

                continue;
            }

            $rule = $this->rule($bucket['rule'] ?? '');

            if ($rule === null) {
                $this->release($key);

                continue;
            }

            if (! $force && ! $this->isDue($bucket, $rule)) {
                continue;
            }

            // Claim the bucket before sending so a scheduled run and an
            // opportunistic flush cannot both release it.
            if (! $this->claim($key)) {
                continue;
            }

            $representative = $this->representative($bucket);

            if ($representative === null) {
                $this->release($key);

                continue;
            }

            $sender(
                (string) ($bucket['rule'] ?? ''),
                $rule,
                (string) ($bucket['channel'] ?? 'mail'),
                $representative,
                (int) ($bucket['count'] ?? 1),
            );

            $this->release($key);
            $released++;
        }

        return $released;
    }

    public function pendingCount(): int
    {
        $pending = 0;

        foreach ($this->indexedKeys() as $key) {
            $bucket = $this->cache()->get($key);

            if (is_array($bucket)) {
                $pending += (int) ($bucket['count'] ?? 0);
            }
        }

        return $pending;
    }

    /**
     * Whether any configured rule is a digest rule, so callers can skip the
     * opportunistic flush entirely when the feature is unused.
     */
    public static function hasDigestRules(): bool
    {
        foreach (ActivityAlertRules::all() as $rule) {
            if (data_get($rule, 'digest', false)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    public static function isDigestRule(array $rule): bool
    {
        return (bool) data_get($rule, 'digest', false);
    }

    /**
     * @param  array<string, mixed>  $bucket
     * @param  array<string, mixed>  $rule
     */
    protected function isDue(array $bucket, array $rule): bool
    {
        $openedAt = $bucket['opened_at'] ?? null;

        if (! is_string($openedAt)) {
            return true;
        }

        return Carbon::parse($openedAt)
            ->addMinutes($this->windowMinutes($rule))
            ->isPast();
    }

    /**
     * @param  array<string, mixed>  $bucket
     */
    protected function representative(array $bucket): ?ActivityContract
    {
        $ids = $bucket['ids'] ?? [];

        if (! is_array($ids) || $ids === []) {
            return null;
        }

        $model = ActivitylogServiceProvider::determineActivityModel();

        /** @var ActivityContract|null $activity */
        $activity = $model::query()->find(end($ids));

        return $activity;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function rule(string $ruleName): ?array
    {
        return ActivityAlertRules::get($ruleName);
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    protected function windowMinutes(array $rule): int
    {
        return max(1, (int) data_get($rule, 'digest_minutes', 60));
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    protected function bucketTtl(array $rule): Carbon
    {
        return now()->addMinutes($this->windowMinutes($rule) + static::BUCKET_GRACE_MINUTES);
    }

    protected function sampleSize(): int
    {
        return max(1, (int) config('filament-logger.alerts.digest_sample_size', 25));
    }

    protected function bucketKey(string $ruleName, string $channel): string
    {
        return static::PREFIX.hash('sha256', $ruleName.'|'.$channel);
    }

    /**
     * @return array<int, string>
     */
    protected function indexedKeys(): array
    {
        $keys = $this->cache()->get(static::INDEX_KEY, []);

        return is_array($keys) ? array_values(array_unique($keys)) : [];
    }

    protected function rememberKey(string $key): void
    {
        $keys = $this->indexedKeys();

        if (in_array($key, $keys, true)) {
            return;
        }

        $keys[] = $key;

        $this->cache()->forever(static::INDEX_KEY, $keys);
    }

    protected function forgetKey(string $key): void
    {
        $keys = array_values(array_filter(
            $this->indexedKeys(),
            fn (string $indexed): bool => $indexed !== $key,
        ));

        $this->cache()->forever(static::INDEX_KEY, $keys);
    }

    protected function claim(string $key): bool
    {
        $store = $this->cache()->getStore();

        // Stores without locking (for example the null store) simply skip the
        // guard; the worst case is a duplicate digest, not a lost one.
        if (! $store instanceof LockProvider) {
            return true;
        }

        return $store->lock($key.':lock', 30)->get();
    }

    protected function release(string $key): void
    {
        $this->cache()->forget($key);
        $this->forgetKey($key);
    }

    protected function cache(): CacheRepository
    {
        $store = config('filament-logger.alerts.cache_store');

        return filled($store) ? Cache::store((string) $store) : Cache::store();
    }
}
