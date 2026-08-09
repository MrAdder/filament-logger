<?php

namespace MrAdder\FilamentLogger\Resources\ActivityResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select as FormSelect;
use Filament\Forms\Components\TextInput as FormTextInput;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use MrAdder\FilamentLogger\Models\ExportPreset;
use MrAdder\FilamentLogger\Support\ActivityExporter;
use MrAdder\FilamentLogger\Support\ActivityExportPresetManager;
use MrAdder\FilamentLogger\Support\ActivityFilterPresetManager;
use MrAdder\FilamentLogger\Support\ActivityReviewLink;
use MrAdder\FilamentLogger\Support\ActivityReviewPlaybookManager;
use MrAdder\FilamentLogger\Widgets\ActivityOverviewWidget;
use MrAdder\FilamentLogger\Widgets\ActivityTrendChartWidget;
use MrAdder\FilamentLogger\Widgets\HighRiskActionsChartWidget;
use MrAdder\FilamentLogger\Widgets\TopEventsChartWidget;
use MrAdder\FilamentLogger\Widgets\TopUsersChartWidget;
use Spatie\Activitylog\ActivitylogServiceProvider;
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
                ->label(static::actionLabel('playbooks'))
                ->icon('heroicon-o-book-open');
        }

        if (! config('filament-logger.exports.enabled', true) || ! static::canExport()) {
            return $actions;
        }

        $exportActions = [
            Action::make('exportCsv')
                ->label(static::actionLabel('export_csv'))
                ->icon('heroicon-o-table-cells')
                ->action(fn (): StreamedResponse => $this->exportCsv()),
            Action::make('exportJson')
                ->label(static::actionLabel('export_json'))
                ->icon('heroicon-o-code-bracket')
                ->action(fn (): StreamedResponse => $this->exportJson()),
        ];

        // Export using a saved preset (select + export)
        $presetOptions = ActivityExportPresetManager::options();

        if ($presetOptions !== []) {
            $exportActions[] = $this->makePresetExportAction('exportCsvWithPreset', static::actionLabel('export_csv_preset'), 'csv', $presetOptions);
            $exportActions[] = $this->makePresetExportAction('exportJsonWithPreset', static::actionLabel('export_json_preset'), 'json', $presetOptions);
        }

        // Allow saving the current view as a DB preset when enabled
        if (config('filament-logger.exports.db_presets_enabled', false)) {
            $exportActions[] = Action::make('saveExportPreset')
                ->label(static::actionLabel('save_export_preset'))
                ->schema([
                    FormTextInput::make('key')
                        ->label(static::fieldLabel('key'))
                        ->required(),
                    FormTextInput::make('label')
                        ->label(static::fieldLabel('label'))
                        ->required(),
                    FormTextInput::make('icon')
                        ->label(static::fieldLabel('icon')),
                    FormSelect::make('columns')
                        ->label(static::fieldLabel('columns'))
                        ->multiple()
                        ->options(ActivityExportPresetManager::columnOptions())
                        ->required(),
                ])
                ->visible(fn (): bool => static::canManageExportPresets())
                ->action(function (array $data): void {
                    abort_unless(static::canManageExportPresets(), 403);

                    ExportPreset::create([
                        'key' => $data['key'],
                        'label' => $data['label'],
                        'icon' => $data['icon'] ?? null,
                        'columns' => $data['columns'],
                        'filters' => $this->currentFilterState(),
                        'visibility' => 'global',
                        'created_by' => optional(auth()->user())->id ?? null,
                    ]);
                });
        }

        $actions[] = ActionGroup::make($exportActions)
            ->label(static::actionLabel('export'))
            ->icon('heroicon-o-arrow-down-tray');

        return $actions;
    }

    /**
     * Exports bypass table pagination, so they need their own gate rather than
     * inheriting whatever let the user open the resource.
     */
    public static function canExport(): bool
    {
        $ability = config('filament-logger.exports.ability', 'exportActivity');

        if (! is_string($ability) || blank($ability)) {
            return true;
        }

        return Gate::allows($ability, ActivitylogServiceProvider::determineActivityModel());
    }

    public static function canManageExportPresets(): bool
    {
        $ability = config('filament-logger.exports.manage_ability', 'manageExportPresets');

        if (! is_string($ability) || blank($ability)) {
            return true;
        }

        return Gate::allows($ability, ExportPreset::class);
    }

    protected static function actionLabel(string $key): string
    {
        return __("filament-logger::filament-logger.action.{$key}");
    }

    protected static function fieldLabel(string $key): string
    {
        return __("filament-logger::filament-logger.field.{$key}");
    }

    /**
     * @return array<string, mixed>
     */
    public function getTabs(): array
    {
        $tabs = [];

        foreach (ActivityFilterPresetManager::saved() as $key => $preset) {
            $tabs[$key] = $this->makeTab(data_get($preset, 'label', $this->generateTabLabel((string) $key)))
                ->icon(data_get($preset, 'icon'))
                ->modifyQueryUsing(fn (Builder $query): Builder => ActivityFilterPresetManager::apply($query, $preset));
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
        // Public Livewire methods are reachable from the browser regardless of
        // which actions are rendered, so the gate is enforced here too.
        abort_unless(config('filament-logger.exports.enabled', true) && static::canExport(), 403);

        $columns = config('filament-logger.exports.columns');

        return app(ActivityExporter::class)->toCsv($this->getTableQueryForExport(), $columns, $this->buildExportMetadata());
    }

    public function exportJson(): StreamedResponse
    {
        abort_unless(config('filament-logger.exports.enabled', true) && static::canExport(), 403);

        $columns = config('filament-logger.exports.columns');

        return app(ActivityExporter::class)->toJson($this->getTableQueryForExport(), $columns, $this->buildExportMetadata());
    }

    /**
     * @return mixed
     */
    abstract protected function makeTab(string $label);

    /**
     * @param  array<string, string>  $presetOptions
     */
    protected function makePresetExportAction(string $name, string $label, string $format, array $presetOptions): Action
    {
        return Action::make($name)
            ->label($label)
            ->schema([
                FormSelect::make('preset')
                    ->label(static::fieldLabel('preset'))
                    ->options($presetOptions),
            ])
            ->action(fn (array $data): StreamedResponse => $this->runPresetExport($format, $data));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function runPresetExport(string $format, array $data): StreamedResponse
    {
        abort_unless(static::canExport(), 403);

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

        if ($format === 'json') {
            return app(ActivityExporter::class)->toJson($query, $columns, $metadata);
        }

        return app(ActivityExporter::class)->toCsv($query, $columns, $metadata);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function buildExportMetadata(array $overrides = []): array
    {
        $columns = config('filament-logger.exports.columns');

        $metadata = [
            'exported_at' => now()->toIso8601String(),
            'exported_by' => optional(auth()->user())->id ?? null,
            'exported_by_name' => optional(auth()->user())->name ?? null,
            'columns' => $columns,
            'filters' => $this->currentFilterState(),
            'source' => static::getResource(),
        ];

        return array_merge($metadata, $overrides);
    }

    /**
     * The applied table filters, which describe the exported slice far more
     * accurately than the raw query string.
     *
     * @return array<string, mixed>
     */
    protected function currentFilterState(): array
    {
        return array_filter($this->tableFilters ?? [], fn (mixed $value): bool => filled($value));
    }
}
