<?php

namespace MrAdder\FilamentLogger\Widgets\Concerns;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use MrAdder\FilamentLogger\Support\ActivityReviewLink;

trait HasActivityReviewDrillDownHeading
{
    protected function activityReviewHeading(string $heading, string $preset): string | Htmlable | null
    {
        $url = ActivityReviewLink::toSavedPreset($preset);

        if (! $url) {
            return $heading;
        }

        return new HtmlString('<a href="'.e($url).'">'.e($heading).'</a>');
    }

    protected function activityReviewHeadingForPlaybook(string $heading, string $playbook): string | Htmlable | null
    {
        $url = ActivityReviewLink::toPlaybook($playbook);

        if (! $url) {
            return $heading;
        }

        return new HtmlString('<a href="'.e($url).'">'.e($heading).'</a>');
    }
}
