<?php

namespace MrAdder\FilamentLogger\Resources\ActivityResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select as FormSelect;
use Filament\Forms\Components\TextInput as FormTextInput;
use MrAdder\FilamentLogger\Models\ExportPreset;
use MrAdder\FilamentLogger\Support\ActivityExportPresetManager;
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

        $exportActions = [
                Action::make('exportCsv')
                    ->label('Export CSV')
                    ->icon('heroicon-o-table-cells')
                    ->action(fn (): StreamedResponse => $this->exportCsv()),
                Action::make('exportJson')
                    ->label('Export JSON')
                    ->icon('heroicon-o-code-bracket')
                    ->action(fn (): StreamedResponse => $this->exportJson()),
            ];

        // Export using a saved preset (select + export)
        $presetOptions = ActivityExportPresetManager::options();

        if ($presetOptions !== []) {
            $exportActions[] = Action::make('exportCsvWithPreset')
                ->label('Export CSV (preset)')
                ->schema([
                    FormSelect::make('preset')
                        ->label('Preset')
                        ->options($presetOptions),
                ])
                ->action(function (array $data): StreamedResponse {
                    $key = $data['preset'] ?? null;
                    $preset = ActivityExportPresetManager::saved()[$key] ?? null;
                    $columns = $preset['columns'] ?? config('filament-logger.exports.columns');
                    $query = $this->getTableQueryForExport();

                    if ($preset) {
                        ActivityExportPresetManager::apply($query, $preset);
                    }

                    $metadata = $this->buildExportMetadata([
                        'preset' => $key,
                        'embed' => config('filament-logger.exports.embed_metadata', false),
                    ]);

                    return app(ActivityExporter::class)->toCsv($query, $columns, $metadata);
                });

            $exportActions[] = Action::make('exportJsonWithPreset')
                ->label('Export JSON (preset)')
                ->schema([
                    FormSelect::make('preset')
                        ->label('Preset')
                        ->options($presetOptions),
                ])
                ->action(function (array $data): StreamedResponse {
                    $key = $data['preset'] ?? null;
                    $preset = ActivityExportPresetManager::saved()[$key] ?? null;
                    $columns = $preset['columns'] ?? config('filament-logger.exports.columns');
                    $query = $this->getTableQueryForExport();

                    if ($preset) {
                        ActivityExportPresetManager::apply($query, $preset);
                    }

                    $metadata = $this->buildExportMetadata([
                        'preset' => $key,
                        'embed' => config('filament-logger.exports.embed_metadata', false),
                    ]);

                    return app(ActivityExporter::class)->toJson($query, $columns, $metadata);
                });
        }

        // Allow saving the current view as a DB preset when enabled
        if (config('filament-logger.exports.db_presets_enabled', false)) {
            $exportActions[] = Action::make('saveExportPreset')
                ->label('Save Export Preset')
                ->schema([
                    FormTextInput::make('key')->required(),
                    FormTextInput::make('label')->required(),
                    FormTextInput::make('icon'),
                    FormSelect::make('columns')
                        ->label('Columns')
                        ->multiple()
                        ->options(collect(config('filament-logger.exports.columns'))->mapWithKeys(fn($c) => [$c => $c])->toArray())
                        ->required(),
                ])
                ->visible(fn () => optional(auth()->user())->can(config('filament-logger.exports.manage_ability', 'manageExportPresets')))
                ->action(function (array $data): void {
                    ExportPreset::create([
                        'key' => $data['key'],
                        'label' => $data['label'],
                        'icon' => $data['icon'] ?? null,
                        'columns' => $data['columns'],
                        'filters' => request()->query(),
                        'visibility' => 'global',
                        'created_by' => optional(auth()->user())->id ?? null,
                    ]);
                });
        }

        $actions[] = ActionGroup::make($exportActions)
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
        $columns = config('filament-logger.exports.columns');

        return app(ActivityExporter::class)->toCsv($this->getTableQueryForExport(), $columns, $this->buildExportMetadata());
    }

    public function exportJson(): StreamedResponse
    {
        $columns = config('filament-logger.exports.columns');

        return app(ActivityExporter::class)->toJson($this->getTableQueryForExport(), $columns, $this->buildExportMetadata());
    }

    abstract protected function makeTab(string $label);

    protected function buildExportMetadata(array $overrides = []): array
    {
        $columns = config('filament-logger.exports.columns');

        $metadata = [
            'exported_at' => now()->toIso8601String(),
            'exported_by' => optional(auth()->user())->id ?? null,
            'exported_by_name' => optional(auth()->user())->name ?? null,
            'columns' => $columns,
            'filters' => request()->query(),
            'source' => static::getResource(),
        ];

        return array_merge($metadata, $overrides);
    }
}
