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
    public function __construct(
        protected ActivityRiskResolver $riskResolver,
        protected ActivityAlertDispatcher $alertDispatcher,
    ) {}

    /**
     * @param  array<string, mixed>  $properties
     * @param  array<int, string>  $tags
     * @param  array<int, string>  $riskReasons
     */
    public function log(
        string $event,
        ?string $description = null,
        array $properties = [],
        ?string $logName = null,
        Model | int | string | null $causer = null,
        ?Model $subject = null,
        bool $anonymous = false,
        ?DateTimeInterface $createdAt = null,
        ?string $risk = null,
        array $tags = [],
        array $riskReasons = [],
    ): ?ActivityContract {
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
