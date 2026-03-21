<?php

namespace MrAdder\FilamentLogger\Support;

use Throwable;

final class ActivityReviewLink
{
    public static function toSavedPreset(string $preset): ?string
    {
        $resource = config('filament-logger.activity_resource');

        if (! is_string($resource) || ! class_exists($resource) || ! is_callable([$resource, 'getUrl'])) {
            return null;
        }

        try {
            return $resource::getUrl('index', ['activeTab' => $preset]);
        } catch (Throwable) {
            return null;
        }
    }

    private function __construct()
    {
        // This class only exposes static helpers and should not be instantiated.
    }
}
