<?php

namespace MrAdder\FilamentLogger\Resources\ActivityResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select as FormSelect;
use Filament\Forms\Components\TextInput as FormTextInput;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use MrAdder\FilamentLogger\Jobs\GenerateActivityExport;
use MrAdder\FilamentLogger\Models\ExportPreset;
use MrAdder\FilamentLogger\Support\ActivityDisplay;
use MrAdder\FilamentLogger\Support\ActivityExportCriteria;
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
            $exportActions[] = $this->configureActionSchema(
                Action::make('saveExportPreset')->label(static::actionLabel('save_export_preset')),
                [
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
                ],
            )
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
        return ActivityDisplay::resolveWidgets($this->defaultHeaderWidgets());
    }

    /**
     * @return array<int, mixed>
     */
    protected function defaultHeaderWidgets(): array
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

    public function exportCsv(): ?StreamedResponse
    {
        return $this->export('csv');
    }

    public function exportJson(): ?StreamedResponse
    {
        return $this->export('json');
    }

    /**
     * Run an export, handing it to the queue when it is large enough to warrant
     * it. Returns null when the export was queued, because there is no file to
     * stream back yet.
     *
     * @param  array<int, string>|null  $columns
     * @param  array<string, mixed>  $metadataOverrides
     */
    protected function export(string $format, ?array $columns = null, array $metadataOverrides = []): ?StreamedResponse
    {
        // Public Livewire methods are reachable from the browser regardless of
        // which actions are rendered, so the gate is enforced here too.
        abort_unless(config('filament-logger.exports.enabled', true) && static::canExport(), 403);

        $columns ??= config('filament-logger.exports.columns');
        $metadata = $this->buildExportMetadata($metadataOverrides);
        $query = $this->getTableQueryForExport();

        if ($this->shouldQueueExport($query)) {
            $this->dispatchQueuedExport($format, $columns, $metadata);

            return null;
        }

        $exporter = app(ActivityExporter::class);

        return $format === 'json'
            ? $exporter->toJson($query, $columns, $metadata)
            : $exporter->toCsv($query, $columns, $metadata);
    }

    /**
     * @param  Builder<Model>  $query
     */
    protected function shouldQueueExport(Builder $query): bool
    {
        if (! config('filament-logger.exports.queue.enabled', false)) {
            return false;
        }

        $threshold = (int) config('filament-logger.exports.queue.threshold', 5000);

        // A threshold of zero means always queue, which avoids paying for a
        // count on installs that never want a synchronous export.
        if ($threshold <= 0) {
            return true;
        }

        return (clone $query)->count() > $threshold;
    }

    /**
     * @param  array<int, string>|null  $columns
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>|null  $criteria
     */
    protected function dispatchQueuedExport(string $format, ?array $columns, array $metadata, ?array $criteria = null): void
    {
        $user = auth()->user();

        $job = new GenerateActivityExport(
            format: $format,
            criteria: $criteria ?? ActivityExportCriteria::fromTableFilters($this->tableFilters ?? [])->toArray(),
            columns: $columns,
            metadata: $metadata,
            userId: $user?->getAuthIdentifier(),
            userClass: $user ? $user::class : null,
        );

        dispatch(
            $job->onConnection(config('filament-logger.exports.queue.connection'))
                ->onQueue(config('filament-logger.exports.queue.name')),
        );

        $this->notifyExportQueued();
    }

    /**
     * Immediate in-panel feedback that the export is running, so the user is
     * not left wondering why no download started.
     */
    protected function notifyExportQueued(): void
    {
        $class = 'Filament\\Notifications\\Notification';

        if (! class_exists($class)) {
            return;
        }

        $class::make()
            ->title(__('filament-logger::filament-logger.export.queued_title'))
            ->body(__('filament-logger::filament-logger.export.queued_body'))
            ->success()
            ->send();
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

    /**
     * @param  array<string, string>  $presetOptions
     */
    protected function makePresetExportAction(string $name, string $label, string $format, array $presetOptions): Action
    {
        return $this->configureActionSchema(
            Action::make($name)->label($label),
            [
                FormSelect::make('preset')
                    ->label(static::fieldLabel('preset'))
                    ->options($presetOptions),
            ],
        )
            ->action(fn (array $data): ?StreamedResponse => $this->runPresetExport($format, $data));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function runPresetExport(string $format, array $data): ?StreamedResponse
    {
        abort_unless(config('filament-logger.exports.enabled', true) && static::canExport(), 403);

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

        if ($this->shouldQueueExport($query)) {
            // Preset filters are folded into the criteria so the queued run
            // reproduces the same slice.
            $criteria = ActivityExportCriteria::fromTableFilters($this->tableFilters ?? [])->toArray();

            $this->dispatchQueuedExport($format, $columns, $metadata + ['preset' => $key], $criteria + [
                'preset' => $key,
            ]);

            return null;
        }

        $exporter = app(ActivityExporter::class);

        return $format === 'json'
            ? $exporter->toJson($query, $columns, $metadata)
            : $exporter->toCsv($query, $columns, $metadata);
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
