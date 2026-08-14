<?php

namespace MrAdder\FilamentLogger\Resources\ActivityResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use MrAdder\FilamentLogger\Resources\ActivityResource\Pages\Concerns\HandlesActivityExports;
use MrAdder\FilamentLogger\Support\ActivityDisplay;
use MrAdder\FilamentLogger\Support\ActivityFilterPresetManager;
use MrAdder\FilamentLogger\Support\ActivityReviewLink;
use MrAdder\FilamentLogger\Support\ActivityReviewPlaybookManager;
use MrAdder\FilamentLogger\Widgets\ActivityOverviewWidget;
use MrAdder\FilamentLogger\Widgets\ActivityTrendChartWidget;
use MrAdder\FilamentLogger\Widgets\HighRiskActionsChartWidget;
use MrAdder\FilamentLogger\Widgets\TopEventsChartWidget;
use MrAdder\FilamentLogger\Widgets\TopUsersChartWidget;

abstract class BaseListActivities extends ListRecords
{
    use HandlesActivityExports;

    public static function getResource(): string
    {
        return config('filament-logger.activity_resource');
    }

    /**
     * @return array<int, mixed>
     */
    protected function getHeaderActions(): array
    {
        $actions = [];

        $playbookActions = $this->playbookHeaderActions();

        if ($playbookActions !== []) {
            $actions[] = ActionGroup::make($playbookActions)
                ->label(static::actionLabel('playbooks'))
                ->icon('heroicon-o-book-open');
        }

        $exportActions = $this->exportHeaderActions();

        if ($exportActions !== []) {
            $actions[] = ActionGroup::make($exportActions)
                ->label(static::actionLabel('export'))
                ->icon('heroicon-o-arrow-down-tray');
        }

        return $actions;
    }

    /**
     * @return array<int, Action>
     */
    protected function playbookHeaderActions(): array
    {
        return collect(ActivityReviewPlaybookManager::all())
            ->map(function (array $playbook, string $key): ?Action {
                $url = ActivityReviewLink::toPlaybook($key);

                if (! $url) {
                    return null;
                }

                return Action::make('playbook_'.$key)
                    ->label((string) data_get($playbook, 'label', ActivityReviewPlaybookManager::label($key)))
                    ->icon(data_get($playbook, 'icon'))
                    ->url($url);
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function getTabs(): array
    {
        return ActivityDisplay::resolveTabs($this->defaultTabs());
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultTabs(): array
    {
        $tabs = [];

        foreach (ActivityFilterPresetManager::saved() as $key => $preset) {
            $tabs[$key] = $this->makeTab(data_get($preset, 'label', $this->generateTabLabel((string) $key)))
                ->icon(data_get($preset, 'icon'))
                ->modifyQueryUsing(fn (Builder $query): Builder => ActivityFilterPresetManager::apply($query, $preset));
        }

        return $tabs;
    }

    /**
     * @return array<int, mixed>
     */
    protected function getHeaderWidgets(): array
    {
        return ActivityDisplay::resolveWidgets($this->defaultDashboardWidgets());
    }

    /**
     * @return array<int, mixed>
     */
    protected function defaultDashboardWidgets(): array
    {
        if (! config('filament-logger.dashboard.enabled', true)) {
            return [];
        }

        $days = (int) config('filament-logger.dashboard.lookback_days', 30);
        $limit = (int) config('filament-logger.dashboard.top_limit', 5);

        return [
            ActivityOverviewWidget::make(['days' => $days]),
            ActivityTrendChartWidget::make(['days' => $days]),
            TopUsersChartWidget::make(['days' => $days, 'limit' => $limit]),
            TopEventsChartWidget::make(['days' => $days, 'limit' => $limit]),
            HighRiskActionsChartWidget::make(['days' => $days, 'limit' => $limit]),
        ];
    }

    /**
     * @return mixed
     */
    abstract protected function makeTab(string $label);

    /**
     * Attach a form schema to an action.
     *
     * Filament 3 actions take their fields through form(); schema() only exists
     * from Filament 4 onwards, so the call is deferred to the version-specific
     * page class.
     *
     * @param  array<int, mixed>  $schema
     */
    abstract protected function configureActionSchema(Action $action, array $schema): Action;

    protected static function actionLabel(string $key): string
    {
        return __("filament-logger::filament-logger.action.{$key}");
    }

    protected static function fieldLabel(string $key): string
    {
        return __("filament-logger::filament-logger.field.{$key}");
    }
}
