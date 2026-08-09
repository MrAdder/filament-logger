<?php

namespace MrAdder\FilamentLogger\Support;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use MrAdder\FilamentLogger\Jobs\SendActivityAlertWebhook;
use MrAdder\FilamentLogger\Notifications\SensitiveActivityAlertNotification;
use Spatie\Activitylog\Contracts\Activity as ActivityContract;
use Throwable;

class ActivityAlertDispatcher
{
    public function __construct(
        protected ActivityRiskResolver $riskResolver,
    ) {}

    public function dispatch(ActivityContract $activity): void
    {
        if (! config('filament-logger.alerts.enabled', false)) {
            return;
        }

        foreach (config('filament-logger.alerts.rules', []) as $ruleName => $rule) {
            if (! data_get($rule, 'enabled', true)) {
                continue;
            }

            if (! $this->ruleMatches($activity, $rule)) {
                continue;
            }

            $title = data_get($rule, 'label', Str::headline((string) $ruleName));

            foreach ($this->channelsForRule($rule) as $channel) {
                $this->dispatchRuleChannel($ruleName, $rule, $channel, $activity, $title);
            }
        }
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
        string $title,
    ): void {
        $usesCooldown = $this->cooldownSecondsForRule($rule) > 0;

        if ($usesCooldown && ! $this->claimCooldown($ruleName, $rule, $channel, $activity)) {
            return;
        }

        try {
            $dispatched = $this->dispatchToChannel($channel, $activity, $title);
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

    protected function dispatchToChannel(string $channel, ActivityContract $activity, string $title): bool
    {
        return match ($channel) {
            'mail' => $this->sendMail($activity, $title),
            'slack' => $this->sendWebhook(config('filament-logger.alerts.slack.webhook_url'), $this->formatWebhookMessage($activity, $title)),
            'discord' => $this->sendWebhook(config('filament-logger.alerts.discord.webhook_url'), [
                'content' => $this->formatWebhookMessage($activity, $title)['text'],
            ]),
            default => false,
        };
    }

    protected function sendMail(ActivityContract $activity, string $title): bool
    {
        $recipients = array_values(array_filter(config('filament-logger.alerts.mail.to', [])));

        if ($recipients === []) {
            return false;
        }

        $notifiable = Notification::route('mail', $recipients);
        $notification = new SensitiveActivityAlertNotification($activity, $title);

        // The notification implements ShouldQueue, so notifyNow() is what keeps
        // the previous synchronous behaviour available.
        $this->shouldQueue()
            ? $notifiable->notify($notification)
            : $notifiable->notifyNow($notification);

        return true;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function sendWebhook(?string $url, array $payload): bool
    {
        if (blank($url)) {
            return false;
        }

        if ($this->shouldQueue()) {
            $job = new SendActivityAlertWebhook($url, $payload, $this->webhookTimeout());

            dispatch($job->onConnection($this->queueConnection())->onQueue($this->queueName()));

            return true;
        }

        $response = Http::timeout($this->webhookTimeout())->post($url, $payload);

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
     * @return array{text: string}
     */
    protected function formatWebhookMessage(ActivityContract $activity, string $title): array
    {
        $subjectType = data_get($activity, 'subject_type');
        $subjectId = data_get($activity, 'subject_id');
        $causerType = data_get($activity, 'causer_type');
        $causerId = data_get($activity, 'causer_id');

        $subject = $subjectType
            ? class_basename((string) $subjectType).' #'.$subjectId
            : 'None';

        $causer = $causerType
            ? class_basename((string) $causerType).' #'.$causerId
            : 'Anonymous';

        return [
            'text' => implode("\n", array_filter([
                $title,
                data_get($activity, 'description'),
                'Event: '.(data_get($activity, 'event') ?? '-'),
                'Log: '.(data_get($activity, 'log_name') ?? '-'),
                'Risk: '.($this->riskResolver->resolveForActivity($activity) ?? '-'),
                'Subject: '.$subject,
                'Causer: '.$causer,
            ])),
        ];
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
