<?php

namespace MrAdder\FilamentLogger\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use MrAdder\FilamentLogger\Notifications\SensitiveActivityAlertNotification;
use Spatie\Activitylog\Contracts\Activity as ActivityContract;

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
                $this->dispatchToChannel($channel, $activity, $title);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    protected function ruleMatches(ActivityContract $activity, array $rule): bool
    {
        $rule['risk'] ??= $this->resolveImplicitRiskFilter($rule);

        if (! ActivityFilterPresetManager::matches($activity, $rule)) {
            return false;
        }

        if (data_get($rule, 'type') !== 'threshold') {
            return true;
        }

        $threshold = (int) data_get($rule, 'threshold', 0);
        $createdAt = data_get($activity, 'created_at');

        if ($threshold < 1 || blank($createdAt) || ! method_exists($activity, 'newQuery')) {
            return false;
        }

        $windowMinutes = (int) data_get($rule, 'window_minutes', 10);
        /** @var \Illuminate\Database\Eloquent\Model&ActivityContract $activity */
        $query = $activity->newQuery()
            ->where('created_at', '<=', $createdAt)
            ->where('created_at', '>=', $createdAt->copy()->subMinutes($windowMinutes));

        ActivityFilterPresetManager::apply($query, $rule);

        return $query->count() === $threshold;
    }

    /**
     * @param  array<string, mixed>  $rule
     * @return array<int, string>
     */
    protected function channelsForRule(array $rule): array
    {
        return array_values(array_unique(data_get($rule, 'channels', config('filament-logger.alerts.default_channels', ['mail']))));
    }

    protected function dispatchToChannel(string $channel, ActivityContract $activity, string $title): void
    {
        match ($channel) {
            'mail' => $this->sendMail($activity, $title),
            'slack' => $this->sendWebhook(config('filament-logger.alerts.slack.webhook_url'), $this->formatWebhookMessage($activity, $title)),
            'discord' => $this->sendWebhook(config('filament-logger.alerts.discord.webhook_url'), [
                'content' => $this->formatWebhookMessage($activity, $title)['text'],
            ]),
            default => null,
        };
    }

    protected function sendMail(ActivityContract $activity, string $title): void
    {
        $recipients = array_values(array_filter(config('filament-logger.alerts.mail.to', [])));

        if ($recipients === []) {
            return;
        }

        Notification::route('mail', $recipients)
            ->notify(new SensitiveActivityAlertNotification($activity, $title));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function sendWebhook(?string $url, array $payload): void
    {
        if (blank($url)) {
            return;
        }

        Http::timeout(5)->post($url, $payload);
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
}
