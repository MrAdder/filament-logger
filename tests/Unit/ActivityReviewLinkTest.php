<?php

use MrAdder\FilamentLogger\Support\ActivityReviewLink;
use MrAdder\FilamentLogger\Widgets\HighRiskActionsChartWidget;
use MrAdder\FilamentLogger\Widgets\TopEventsChartWidget;

const ACTIVITY_REVIEW_ACTIVE_TAB_HIGH_RISK = 'activeTab=high_risk';
const ACTIVITY_REVIEW_TABLE_FILTERS = 'tableFilters';

class ActivityReviewLinkFakeResource
{
    /**
     * @param  array<int, mixed>  $arguments
     */
    public static function getUrl(...$arguments): string
    {
        $parameters = is_array($arguments[1] ?? null) ? $arguments[1] : [];
        $query = http_build_query($parameters);

        return 'https://example.test/activity'.($query !== '' ? '?'.$query : '');
    }
}

it('builds activity resource links for saved presets', function () {
    config()->set('filament-logger.activity_resource', ActivityReviewLinkFakeResource::class);

    $url = ActivityReviewLink::toSavedPreset('high_risk');

    expect($url)
        ->toBeString()
        ->toContain(ACTIVITY_REVIEW_ACTIVE_TAB_HIGH_RISK);
});

it('builds activity resource links for playbooks', function () {
    config()->set('filament-logger.activity_resource', ActivityReviewLinkFakeResource::class);

    $url = ActivityReviewLink::toPlaybook('high_risk_incidents');

    expect($url)
        ->toBeString()
        ->toContain(ACTIVITY_REVIEW_ACTIVE_TAB_HIGH_RISK)
        ->toContain(ACTIVITY_REVIEW_TABLE_FILTERS);
});

it('returns null when no activity resource is configured', function () {
    config()->set('filament-logger.activity_resource', null);

    expect(ActivityReviewLink::toSavedPreset('all'))->toBeNull();
});

it('renders chart headings with drill-down links', function () {
    config()->set('filament-logger.activity_resource', ActivityReviewLinkFakeResource::class);

    $highRiskHeading = (string) (new HighRiskActionsChartWidget())->getHeading();
    $topEventsHeading = (string) (new TopEventsChartWidget())->getHeading();

    expect($highRiskHeading)
        ->toContain(ACTIVITY_REVIEW_ACTIVE_TAB_HIGH_RISK)
        ->and($topEventsHeading)
        ->toContain('activeTab=all');
});
