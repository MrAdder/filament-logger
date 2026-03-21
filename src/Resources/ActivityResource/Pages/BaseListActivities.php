<?php

namespace MrAdder\FilamentLogger\Resources\ActivityResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\ListRecords;
use MrAdder\FilamentLogger\Support\ActivityExporter;
use MrAdder\FilamentLogger\Support\ActivityFilterPresetManager;
use MrAdder\FilamentLogger\Support\ActivityReviewLink;
use MrAdder\FilamentLogger\Support\ActivityReviewPlaybookManager;
use MrAdder\FilamentLogger\Widgets\ActivityOverviewWidget;
use MrAdder\FilamentLogger\Widgets\ActivityTrendChartWidget;
use MrAdder\FilamentLogger\Widgets\HighRiskActionsChartWidget;
use MrAdder\FilamentLogger\Widgets\TopEventsChartWidget;
use MrAdder\FilamentLogger\Widgets\TopUsersChartWidget;
use Symfony\Component\HttpFoundation\StreamedResponse;

abstract class BaseListActivities extends ListRecords
{
    public static function getResource(): string
    {
        return config('filament-logger.activity_resource');
    }

    protected function getHeaderActions(): array
    {
        $actions = [];

        $playbookActions = collect(ActivityReviewPlaybookManager::all())
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

        if ($playbookActions !== []) {
            $actions[] = ActionGroup::make($playbookActions)
                ->label('Playbooks')
                ->icon('heroicon-o-book-open');
        }

        if (! config('filament-logger.exports.enabled', true)) {
            return $actions;
        }

        $actions[] = ActionGroup::make([
                Action::make('exportCsv')
                    ->label('Export CSV')
                    ->icon('heroicon-o-table-cells')
                    ->action(fn (): StreamedResponse => $this->exportCsv()),
                Action::make('exportJson')
                    ->label('Export JSON')
                    ->icon('heroicon-o-code-bracket')
                    ->action(fn (): StreamedResponse => $this->exportJson()),
            ])
            ->label('Export')
            ->icon('heroicon-o-arrow-down-tray');

        return $actions;
    }

    public function getTabs(): array
    {
        $tabs = [];

        foreach (ActivityFilterPresetManager::saved() as $key => $preset) {
            $tabs[$key] = $this->makeTab(data_get($preset, 'label', $this->generateTabLabel((string) $key)))
                ->icon(data_get($preset, 'icon'))
                ->modifyQueryUsing(fn ($query) => ActivityFilterPresetManager::apply($query, $preset));
        }

        return $tabs;
    }

    protected function getHeaderWidgets(): array
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

    public function exportCsv(): StreamedResponse
    {
        return app(ActivityExporter::class)->toCsv($this->getTableQueryForExport());
    }

    public function exportJson(): StreamedResponse
    {
        return app(ActivityExporter::class)->toJson($this->getTableQueryForExport());
    }

    abstract protected function makeTab(string $label);
}
