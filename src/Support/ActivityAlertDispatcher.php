<?php

namespace MrAdder\FilamentLogger\Support;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use MrAdder\FilamentLogger\Jobs\SendActivityAlertWebhook;
use MrAdder\FilamentLogger\Notifications\SensitiveActivityAlertNotification;
use Spatie\Activitylog\Contracts\Activity as ActivityContract;
use Throwable;

class ActivityAlertDispatcher
{
    public function __construct(
        protected ActivityRiskResolver $riskResolver,
        protected ActivityAlertDigest $digest = new ActivityAlertDigest,
    ) {}

    public function dispatch(ActivityContract $activity): void
    {
        if (! config('filament-logger.alerts.enabled', false)) {
            return;
        }

        // Close expired windows before this activity is considered, otherwise
        // it would be folded into a stale bucket and reported under a window it
        // does not belong to. This is also the opportunistic release path for
        // installs that have not scheduled the digest command.
        if (ActivityAlertDigest::hasDigestRules()) {
            $this->flushDigests();
        }

        foreach (ActivityAlertRules::all() as $ruleName => $rule) {
            if (! is_array($rule) || ! data_get($rule, 'enabled', true)) {
                continue;
            }

            if (! $this->ruleMatches($activity, $rule)) {
                continue;
            }

            foreach ($this->channelsForRule($rule) as $channel) {
                if (ActivityAlertDigest::isDigestRule($rule)) {
                    $this->digest->add((string) $ruleName, $rule, $channel, $activity);

                    continue;
                }

                $this->dispatchRuleChannel((string) $ruleName, $rule, $channel, $activity);
            }
        }
    }

    /**
     * Release every digest whose window has closed.
     *
     * @return int Number of digests sent.
     */
    public function flushDigests(bool $force = false): int
    {
        return $this->digest->flushDue(
            function (string $ruleName, array $rule, string $channel, ActivityContract $activity, int $count): void {
                $this->dispatchRuleChannel($ruleName, $rule, $channel, $activity, $count, digest: true);
            },
            $force,
        );
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    protected function ruleMatches(ActivityContract $activity, array $rule): bool
    {
        $rule['risk'] ??= $this->resolveImplicitRiskFilter($rule);
        $matches = ActivityFilterPresetManager::matches($activity, $rule);

        if ($matches && data_get($rule, 'type') === 'threshold') {
            $threshold = (int) data_get($rule, 'threshold', 0);
            $createdAt = data_get($activity, 'created_at');
            $matches = $threshold > 0 && filled($createdAt) && method_exists($activity, 'newQuery');

            if ($matches) {
                $windowMinutes = (int) data_get($rule, 'window_minutes', 10);
                /** @var Model&ActivityContract $activity */
                $query = $activity->newQuery()
                    ->where('created_at', '<=', $createdAt)
                    ->where('created_at', '>=', $createdAt->copy()->subMinutes($windowMinutes));

                ActivityFilterPresetManager::apply($query, $rule);

                // A burst can push the count past the threshold between two
                // activities, so an exact match would silently skip the alert.
                // Repeat alerts are suppressed by the rule cooldown instead.
                $matches = $query->count() >= $threshold;
            }
        }

        return $matches;
    }

    /**
     * @param  array<string, mixed>  $rule
     * @return array<int, string>
     */
    protected function channelsForRule(array $rule): array
    {
        return array_values(array_unique(data_get($rule, 'channels', config('filament-logger.alerts.default_channels', ['mail']))));
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    protected function dispatchRuleChannel(
        string $ruleName,
        array $rule,
        string $channel,
        ActivityContract $activity,
        int $count = 1,
        bool $digest = false,
    ): void {
        // A digest has already waited out its window; applying the cooldown
        // again would suppress the very alert the window was accumulating.
        $usesCooldown = ! $digest && $this->cooldownSecondsForRule($rule) > 0;

        if ($usesCooldown && ! $this->claimCooldown($ruleName, $rule, $channel, $activity)) {
            return;
        }

        $message = ActivityAlertMessage::for(
            $ruleName,
            $digest ? $this->digestRule($rule) : $rule,
            $activity,
            $this->riskResolver->resolveForActivity($activity),
            $count,
        );

        try {
            $dispatched = $this->dispatchToChannel($channel, $rule, $activity, $message);
        } catch (Throwable $exception) {
            if ($usesCooldown) {
                $this->releaseCooldown($ruleName, $rule, $channel, $activity);
            }

            throw $exception;
        }

        if ($usesCooldown && ! $dispatched) {
            $this->releaseCooldown($ruleName, $rule, $channel, $activity);
        }
    }

    /**
     * Digest rules may override the wording for the batched alert; otherwise
     * the rule's normal templates are reused.
     *
     * @param  array<string, mixed>  $rule
     * @return array<string, mixed>
     */
    protected function digestRule(array $rule): array
    {
        if (filled(data_get($rule, 'digest_title'))) {
            $rule['title'] = data_get($rule, 'digest_title');
        }

        $rule['message'] = filled(data_get($rule, 'digest_message'))
            ? data_get($rule, 'digest_message')
            : ':count matching activities in the last :window minutes.'."\n".
              'Latest: :description'."\n".
              'Event: :event'."\n".
              'Log: :log_name';

        return $rule;
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    protected function dispatchToChannel(
        string $channel,
        array $rule,
        ActivityContract $activity,
        ActivityAlertMessage $message,
    ): bool {
        return match ($channel) {
            'mail' => $this->sendMail($activity, $message),
            'slack' => $this->sendWebhook(
                $this->channelUrl($rule, 'slack'),
                ['text' => $message->toText()],
            ),
            'discord' => $this->sendWebhook(
                $this->channelUrl($rule, 'discord'),
                ['content' => $message->toText()],
            ),
            'webhook' => $this->sendWebhook(
                $this->channelUrl($rule, 'webhook'),
                $this->webhookPayload($activity, $message),
                $this->webhookHeaders(),
            ),
            default => false,
        };
    }

    /**
     * A rule may point a channel at its own endpoint, overriding the global one.
     *
     * @param  array<string, mixed>  $rule
     */
    protected function channelUrl(array $rule, string $channel): ?string
    {
        // 'webhook' => rule key `webhook_url`, 'slack' => `slack_url`, etc.
        $override = data_get($rule, $channel.'_url');

        if (filled($override)) {
            return (string) $override;
        }

        $key = $channel === 'webhook'
            ? 'filament-logger.alerts.webhook.url'
            : "filament-logger.alerts.{$channel}.webhook_url";

        $url = config($key);

        return filled($url) ? (string) $url : null;
    }

    /**
     * Structured payload for the generic webhook channel. Slack and Discord
     * keep their own service-specific shapes.
     *
     * @return array<string, mixed>
     */
    protected function webhookPayload(ActivityContract $activity, ActivityAlertMessage $message): array
    {
        $replacements = $message->replacements;

        return [
            'title' => $message->title,
            'message' => $message->body,
            'rule' => $replacements[':rule'],
            'count' => (int) $replacements[':count'],
            'activity' => [
                'id' => $activity instanceof Model ? $activity->getKey() : data_get($activity, 'id'),
                'log_name' => data_get($activity, 'log_name'),
                'event' => data_get($activity, 'event'),
                'description' => data_get($activity, 'description'),
                'risk' => $replacements[':risk'],
                'risk_reasons' => $replacements[':risk_reasons'],
                'subject_type' => data_get($activity, 'subject_type'),
                'subject_id' => data_get($activity, 'subject_id'),
                'causer_type' => data_get($activity, 'causer_type'),
                'causer_id' => data_get($activity, 'causer_id'),
                'logged_at' => $replacements[':logged_at'],
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function webhookHeaders(): array
    {
        $headers = config('filament-logger.alerts.webhook.headers', []);

        return is_array($headers) ? $headers : [];
    }

    protected function sendMail(ActivityContract $activity, ActivityAlertMessage $message): bool
    {
        $recipients = array_values(array_filter(config('filament-logger.alerts.mail.to', [])));

        if ($recipients === []) {
            return false;
        }

        $notifiable = Notification::route('mail', $recipients);
        $notification = new SensitiveActivityAlertNotification($activity, $message->title, $message);

        // The notification implements ShouldQueue, so notifyNow() is what keeps
        // the previous synchronous behaviour available.
        $this->shouldQueue()
            ? $notifiable->notify($notification)
            : $notifiable->notifyNow($notification);

        return true;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     */
    protected function sendWebhook(?string $url, array $payload, array $headers = []): bool
    {
        if (blank($url)) {
            return false;
        }

        if ($this->shouldQueue()) {
            $job = new SendActivityAlertWebhook($url, $payload, $this->webhookTimeout(), $headers);

            dispatch($job->onConnection($this->queueConnection())->onQueue($this->queueName()));

            return true;
        }

        $response = Http::withHeaders($headers)->timeout($this->webhookTimeout())->post($url, $payload);

        // Laravel does not throw on 4xx/5xx by default. Reporting success for a
        // rejected webhook would burn the cooldown and hide the failure.
        return $response->successful();
    }

    protected function shouldQueue(): bool
    {
        return (bool) config('filament-logger.alerts.queue', false);
    }

    protected function queueConnection(): ?string
    {
        $connection = config('filament-logger.alerts.queue_connection');

        return filled($connection) ? (string) $connection : null;
    }

    protected function queueName(): ?string
    {
        $queue = config('filament-logger.alerts.queue_name');

        return filled($queue) ? (string) $queue : null;
    }

    protected function webhookTimeout(): int
    {
        return max(1, (int) config('filament-logger.alerts.webhook_timeout', 5));
    }

    /**
     * @param  array<string, mixed>  $rule
     * @return array<int, string>|null
     */
    protected function resolveImplicitRiskFilter(array $rule): ?array
    {
        if (data_get($rule, 'risk_reasons')) {
            return ['high'];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    protected function claimCooldown(string $ruleName, array $rule, string $channel, ActivityContract $activity): bool
    {
        return $this->cooldownCache()->add(
            $this->cooldownCacheKey($ruleName, $rule, $channel, $activity),
            true,
            now()->addSeconds($this->cooldownSecondsForRule($rule)),
        );
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    protected function releaseCooldown(string $ruleName, array $rule, string $channel, ActivityContract $activity): void
    {
        $this->cooldownCache()->forget($this->cooldownCacheKey($ruleName, $rule, $channel, $activity));
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    protected function cooldownSecondsForRule(array $rule): int
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
    protected function cooldownCacheKey(string $ruleName, array $rule, string $channel, ActivityContract $activity): string
    {
        $pattern = [
            'rule' => $ruleName,
            'channel' => $channel,
            'type' => data_get($rule, 'type'),
            'log_name' => data_get($activity, 'log_name'),
            'event' => data_get($activity, 'event'),
            'risk' => $this->riskResolver->resolveForActivity($activity),
        ];

        return 'filament-logger:alerts:cooldown:'.hash('sha256', (string) json_encode($pattern));
    }

    protected function cooldownCache(): CacheRepository
    {
        $store = config('filament-logger.alerts.cache_store');

        if (filled($store)) {
            return Cache::store((string) $store);
        }

        return Cache::store();
    }
}
