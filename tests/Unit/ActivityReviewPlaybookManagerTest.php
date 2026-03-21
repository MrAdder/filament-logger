<?php

use MrAdder\FilamentLogger\Support\ActivityReviewPlaybookManager;

it('provides built-in investigation playbooks', function () {
    $playbooks = ActivityReviewPlaybookManager::all();

    expect($playbooks)->toHaveKeys([
        'all_activity',
        'high_risk_incidents',
        'auth_anomalies',
        'failed_logins',
        'destructive_actions',
    ]);
});

it('resolves playbook url parameters with optional date preset', function () {
    $highRisk = ActivityReviewPlaybookManager::toUrlParameters('high_risk_incidents');
    $all = ActivityReviewPlaybookManager::toUrlParameters('all_activity');

    expect($highRisk['activeTab'])->toBe('high_risk')
        ->and($highRisk['tableFilters']['created_at']['preset'])->toBe('last_30_days')
        ->and($all['activeTab'])->toBe('all')
        ->and($all)->not->toHaveKey('tableFilters');
});

it('returns empty parameters for unknown playbooks', function () {
    expect(ActivityReviewPlaybookManager::toUrlParameters('unknown-playbook'))->toBe([]);
});
