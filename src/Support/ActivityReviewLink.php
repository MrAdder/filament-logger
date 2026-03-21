<?php

namespace MrAdder\FilamentLogger\Support;

use Throwable;

final class ActivityReviewLink
{
    public static function toSavedPreset(string $preset): ?string
    {
        return self::toUrl([
            'activeTab' => $preset,
        ]);
    }

    public static function toPlaybook(string $playbook): ?string
    {
        $parameters = ActivityReviewPlaybookManager::toUrlParameters($playbook);

        if ($parameters === []) {
            return null;
        }

        return self::toUrl($parameters);
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    protected static function toUrl(array $parameters): ?string
    {
        $resource = config('filament-logger.activity_resource');

        if (! is_string($resource) || ! class_exists($resource) || ! is_callable([$resource, 'getUrl'])) {
            return null;
        }

        try {
            return $resource::getUrl('index', $parameters);
        } catch (Throwable) {
            return null;
        }
    }

    private function __construct()
    {
        // This class only exposes static helpers and should not be instantiated.
    }
}
