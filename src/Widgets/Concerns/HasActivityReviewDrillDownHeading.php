<?php

namespace MrAdder\FilamentLogger\Widgets\Concerns;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use MrAdder\FilamentLogger\Support\ActivityReviewLink;

trait HasActivityReviewDrillDownHeading
{
    protected function activityReviewHeading(string $heading, string $preset): string|Htmlable|null
    {
        return $this->activityReviewHeadingFromUrl($heading, ActivityReviewLink::toSavedPreset($preset));
    }

    protected function activityReviewHeadingForPlaybook(string $heading, string $playbook): string|Htmlable|null
    {
        return $this->activityReviewHeadingFromUrl($heading, ActivityReviewLink::toPlaybook($playbook));
    }

    protected function activityReviewHeadingFromUrl(string $heading, ?string $url): string|Htmlable|null
    {
        if (! $url) {
            return $heading;
        }

        return new HtmlString('<a href="'.e($url).'">'.e($heading).'</a>');
    }
}
