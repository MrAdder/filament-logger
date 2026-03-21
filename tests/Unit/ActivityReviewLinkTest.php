<?php

use MrAdder\FilamentLogger\Support\ActivityReviewLink;
use MrAdder\FilamentLogger\Widgets\HighRiskActionsChartWidget;
use MrAdder\FilamentLogger\Widgets\TopEventsChartWidget;

class ActivityReviewLinkFakeResource
{
    /**
     * @param  array<string, string>  $parameters
     */
    public static function getUrl(string $name = 'index', array $parameters = []): string
    {
        $activeTab = $parameters['activeTab'] ?? 'all';

        return 'https://example.test/activity?activeTab='.$activeTab;
    }
}

it('builds activity resource links for saved presets', function () {
    config()->set('filament-logger.activity_resource', ActivityReviewLinkFakeResource::class);

    $url = ActivityReviewLink::toSavedPreset('high_risk');

    expect($url)
        ->toBeString()
        ->toContain('activeTab=high_risk');
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
        ->toContain('activeTab=high_risk')
        ->and($topEventsHeading)
        ->toContain('activeTab=all');
});
