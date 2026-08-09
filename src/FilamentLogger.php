<?php

namespace MrAdder\FilamentLogger;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use MrAdder\FilamentLogger\Support\ActivityAlertDispatcher;
use MrAdder\FilamentLogger\Support\ActivityRiskResolver;
use MrAdder\FilamentLogger\Support\LogDataSanitizer;
use Spatie\Activitylog\ActivityLogger;
use Spatie\Activitylog\ActivityLogStatus;
use Spatie\Activitylog\Contracts\Activity as ActivityContract;

class FilamentLogger
{
    /**
     * Global override for model activity descriptions.
     *
     * @var (callable(Model, string, string): ?string)|null
     */
    protected static $describeUsing = null;

    public function __construct(
        protected ActivityRiskResolver $riskResolver,
        protected ActivityAlertDispatcher $alertDispatcher,
    ) {}

    /**
     * Customise how model lifecycle activities are described.
     *
     * The callback receives the subject, the event, and the log name, and may
     * return null to fall back to the built-in translated description.
     *
     * @param  (callable(Model, string, string): ?string)|null  $callback
     */
    public static function describeUsing(?callable $callback): void
    {
        static::$describeUsing = $callback;
    }

    public static function resolveDescription(Model $model, string $event, string $logName): ?string
    {
        if (static::$describeUsing === null) {
            return null;
        }

        $description = call_user_func(static::$describeUsing, $model, $event, $logName);

        return is_string($description) && filled($description) ? $description : null;
    }

    /**
     * @param  array{
     *     properties?: array<string, mixed>,
     *     logName?: string|null,
     *     causer?: \Illuminate\Database\Eloquent\Model|int|string|null,
     *     subject?: \Illuminate\Database\Eloquent\Model|null,
     *     anonymous?: bool,
     *     createdAt?: \DateTimeInterface|null,
     *     risk?: string|null,
     *     tags?: array<int, string>,
     *     riskReasons?: array<int, string>
     * }  $options
     */
    public function log(
        string $event,
        ?string $description = null,
        array $options = [],
    ): ?ActivityContract {
        $properties = is_array($options['properties'] ?? null) ? $options['properties'] : [];
        $logName = is_string($options['logName'] ?? null) ? $options['logName'] : null;
        $causer = $options['causer'] ?? null;
        $subject = ($options['subject'] ?? null) instanceof Model ? $options['subject'] : null;
        $anonymous = (bool) ($options['anonymous'] ?? false);
        $createdAt = ($options['createdAt'] ?? null) instanceof DateTimeInterface ? $options['createdAt'] : null;
        $risk = is_string($options['risk'] ?? null) ? $options['risk'] : null;
        $tags = is_array($options['tags'] ?? null) ? $options['tags'] : [];
        $riskReasons = is_array($options['riskReasons'] ?? null) ? $options['riskReasons'] : [];

        $properties = $this->buildProperties(
            properties: $properties,
            event: $event,
            risk: $risk,
            tags: $tags,
            riskReasons: $riskReasons,
        );

        $logger = app(ActivityLogger::class)
            ->useLog($logName ?? config('filament-logger.custom_events.default_log_name', 'Custom'))
            ->setLogStatus(app(ActivityLogStatus::class))
            ->event($event);

        if ($subject instanceof Model && $subject->exists) {
            $logger->performedOn($subject);
        }

        if ($anonymous) {
            $logger->causedByAnonymous();
        } elseif ($causer !== null) {
            $logger->causedBy($causer);
        }

        if ($createdAt !== null) {
            $logger->createdAt($createdAt);
        }

        if ($properties !== []) {
            $logger->withProperties($properties);
        }

        $activity = $logger->log($description ?? $event);

        if ($activity !== null) {
            $this->alertDispatcher->dispatch($activity);
        }

        return $activity;
    }

    /**
     * @param  array<string, mixed>  $properties
     * @param  array<int, string>  $tags
     * @param  array<int, string>  $riskReasons
     * @return array<string, mixed>
     */
    protected function buildProperties(
        array $properties,
        string $event,
        ?string $risk,
        array $tags,
        array $riskReasons,
    ): array {
        $properties = LogDataSanitizer::sanitizeProperties($properties);

        if ($tags !== []) {
            $properties['tags'] = array_values(array_unique(array_filter($tags, static fn (?string $tag): bool => filled($tag))));
        }

        if ($riskReasons !== []) {
            $properties['risk_reasons'] = array_values(array_unique(array_filter($riskReasons, static fn (?string $reason): bool => filled($reason))));
        }

        return $this->riskResolver->enrich($event, $properties, explicitRisk: $risk);
    }
}
