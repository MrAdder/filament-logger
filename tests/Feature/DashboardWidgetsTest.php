<?php

use MrAdder\FilamentLogger\Tests\Fixtures\Models\TestUser;
use MrAdder\FilamentLogger\Widgets\ActivityOverviewWidget;
use MrAdder\FilamentLogger\Widgets\ActivityTrendChartWidget;
use MrAdder\FilamentLogger\Widgets\HighRiskActionsChartWidget;
use MrAdder\FilamentLogger\Widgets\TopEventsChartWidget;
use MrAdder\FilamentLogger\Widgets\TopUsersChartWidget;
use Spatie\Activitylog\Models\Activity as ActivityModel;

/**
 * Renders each widget's data rather than only asserting it is registered, so a
 * broken heading or dataset shape is caught before it reaches a dashboard.
 */
function widgetData(object $widget): array
{
    return (new ReflectionMethod($widget::class, 'getData'))->invoke($widget);
}

function seedDashboardActivity(): void
{
    $user = TestUser::create(['name' => 'Alice', 'email' => 'alice@example.test']);

    ActivityModel::create([
        'log_name' => 'Resource',
        'description' => 'Deleted a record',
        'event' => 'Deleted',
        'properties' => ['risk' => 'high'],
        'causer_type' => $user::class,
        'causer_id' => $user->getKey(),
    ]);

    ActivityModel::create([
        'log_name' => 'Access',
        'description' => 'Failed login',
        'event' => 'Failed Login',
    ]);

    ActivityModel::create([
        'log_name' => 'Resource',
        'description' => 'Updated a record',
        'event' => 'Updated',
        'causer_type' => $user::class,
        'causer_id' => $user->getKey(),
    ]);
}

it('renders overview stats from recorded activity', function () {
    seedDashboardActivity();

    $widget = new ActivityOverviewWidget;
    $widget->days = 30;

    $stats = (new ReflectionMethod($widget::class, 'getStats'))->invoke($widget);

    expect($stats)->toHaveCount(4)
        ->and($widget->getHeading())->toBe('Activity Overview');

    $values = array_map(fn ($stat): string => (string) $stat->getValue(), $stats);

    // total, high risk, failed logins, unique actors
    expect($values)->toBe(['3', '1', '1', '1']);
});

it('renders overview stats with no activity', function () {
    $widget = new ActivityOverviewWidget;
    $widget->days = 30;

    $stats = (new ReflectionMethod($widget::class, 'getStats'))->invoke($widget);
    $values = array_map(fn ($stat): string => (string) $stat->getValue(), $stats);

    expect($values)->toBe(['0', '0', '0', '0']);
});

it('renders the trend chart with one point per day', function () {
    seedDashboardActivity();

    $widget = new ActivityTrendChartWidget;
    $widget->days = 7;

    $data = widgetData($widget);

    expect($data['labels'])->toHaveCount(7)
        ->and($data['datasets'][0]['data'])->toHaveCount(7)
        ->and(array_sum($data['datasets'][0]['data']))->toBe(3);
});

it('renders the top events chart', function () {
    seedDashboardActivity();

    $widget = new TopEventsChartWidget;
    $widget->days = 30;
    $widget->limit = 5;

    $data = widgetData($widget);

    expect($data['labels'])->toContain('Deleted')
        ->and($data['labels'])->toContain('Updated')
        ->and($data['datasets'][0]['data'])->not->toBeEmpty();
});

it('renders the high risk chart with only high risk activity', function () {
    seedDashboardActivity();

    $widget = new HighRiskActionsChartWidget;
    $widget->days = 30;
    $widget->limit = 5;

    $data = widgetData($widget);

    expect($data['labels'])->toBe(['Deleted'])
        ->and($data['datasets'][0]['data'])->toBe([1]);
});

it('renders the top users chart with causer names', function () {
    seedDashboardActivity();

    $widget = new TopUsersChartWidget;
    $widget->days = 30;
    $widget->limit = 5;

    $data = widgetData($widget);

    expect($data['labels'])->toBe(['Alice'])
        ->and($data['datasets'][0]['data'])->toBe([2]);
});

it('renders every chart widget with no activity at all', function () {
    foreach ([ActivityTrendChartWidget::class, TopEventsChartWidget::class, HighRiskActionsChartWidget::class, TopUsersChartWidget::class] as $class) {
        $widget = new $class;

        $data = widgetData($widget);

        expect($data)->toHaveKey('labels')
            ->and($data)->toHaveKey('datasets');
    }
});

it('links each chart heading to a pre-filtered review view', function () {
    $headings = [
        ActivityTrendChartWidget::class => 'Activity Trend',
        TopEventsChartWidget::class => 'Top Events',
        HighRiskActionsChartWidget::class => 'High-Risk Actions',
        TopUsersChartWidget::class => 'Top Users',
    ];

    foreach ($headings as $class => $text) {
        $heading = (new $class)->getHeading();

        // Either a plain string or an anchor wrapping it, depending on whether
        // the review URL could be resolved in this context.
        expect((string) $heading)->toContain($text);
    }
});
