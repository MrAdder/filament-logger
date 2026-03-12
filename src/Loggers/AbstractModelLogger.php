<?php

namespace MrAdder\FilamentLogger\Loggers;

use BadMethodCallException;
use Filament\Facades\Filament;
use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use MrAdder\FilamentLogger\FilamentLogger as FilamentLoggerManager;
use MrAdder\FilamentLogger\Support\LogDataSanitizer;
use MrAdder\FilamentLogger\Support\PreviousAttributesStore;
use MrAdder\FilamentLogger\Support\ReplicationContextStore;

abstract class AbstractModelLogger
{
    protected abstract function getLogName(): string;

    protected function getUserName(?Authenticatable $user): string
    {
        if(blank($user) || $user instanceof GenericUser) {
            return 'Anonymous';
        }

        return Filament::getUserName($user);
    }

    protected function getModelName(Model $model)
    {
        return Str::of(class_basename($model))->headline();
    }

    /**
     * @return array<int, string>
     */
    protected function getIgnoredAttributes(Model $model): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getContextProperties(Model $model): array
    {
        return [];
    }

    protected function getLoggableAttributes(Model $model, mixed $values = []): array
    {
        if (! is_array($values)) {
            return [];
        }

        if (count($model->getVisible()) > 0) {
            $values = array_intersect_key($values, array_flip($model->getVisible()));
        }

        if (count($model->getHidden()) > 0) {
            $values = array_diff_key($values, array_flip($model->getHidden()));
        }

        $ignoredAttributes = $this->getIgnoredAttributes($model);

        if ($ignoredAttributes !== []) {
            $values = array_diff_key($values, array_flip($ignoredAttributes));
        }

        return LogDataSanitizer::sanitizeProperties($values);
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    protected function log(Model $model, string $event, ?string $description = null, array $properties = [])
    {
        if(is_null($description)) {
            $description = $this->getModelName($model).' '.$event;
        }

        if (auth()->check()) {
            $description .= ' by '.$this->getUserName(auth()->user());
        }

        app(FilamentLoggerManager::class)->log(
            event: $event,
            description: $description,
            properties: $properties,
            logName: $this->getLogName(),
            subject: $model,
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function buildProperties(Model $model, array $attributes = [], array $old = [], array $extra = []): array
    {
        $properties = LogDataSanitizer::sanitizeProperties($this->getContextProperties($model));

        if ($old !== []) {
            $old = $this->getLoggableAttributes($model, $old);

            if ($old !== []) {
                $properties['old'] = $old;
            }
        }

        if ($attributes !== []) {
            $attributes = $this->getLoggableAttributes($model, $attributes);

            if ($attributes !== []) {
                $properties['attributes'] = $attributes;
            }
        }

        if ($extra !== []) {
            $properties = array_merge($properties, LogDataSanitizer::sanitizeProperties($extra));
        }

        return array_filter($properties, static fn (mixed $value): bool => filled($value));
    }

    /**
     * @return array<string, mixed>
     */
    protected function getPreviousAttributes(Model $model, bool $forget = false): array
    {
        $storedAttributes = $forget
            ? PreviousAttributesStore::pull($model)
            : PreviousAttributesStore::get($model);

        if ($storedAttributes !== []) {
            return $storedAttributes;
        }

        try {
            return $model->getPrevious();
        } catch (BadMethodCallException) {
            $changes = $model->getChanges();

            return array_intersect_key($model->getOriginal(), $changes);
        }
    }

    protected function isForceDeleting(Model $model): bool
    {
        return method_exists($model, 'isForceDeleting') && $model->isForceDeleting();
    }

    protected function isRestoreUpdate(Model $model, array $changes, array $previous): bool
    {
        if (! method_exists($model, 'getDeletedAtColumn')) {
            return false;
        }

        $deletedAtColumn = $model->getDeletedAtColumn();

        return (array_keys($changes) === [$deletedAtColumn])
            && array_key_exists($deletedAtColumn, $previous)
            && filled($previous[$deletedAtColumn])
            && ($changes[$deletedAtColumn] === null);
    }

    public function created(Model $model)
    {
        $replicationContext = ReplicationContextStore::get($model);

        if ($replicationContext !== null) {
            $this->log(
                $model,
                'Replicated',
                properties: $this->buildProperties(
                    $model,
                    $model->getAttributes(),
                    extra: ['replicated_from' => $replicationContext],
                ),
            );

            return;
        }

        $this->log($model, 'Created', properties: $this->buildProperties($model, $model->getAttributes()));
    }

    public function updating(Model $model): void
    {
        $dirty = $model->getDirty();

        if ($dirty === []) {
            return;
        }

        PreviousAttributesStore::remember(
            $model,
            array_intersect_key($model->getOriginal(), $dirty),
        );
    }

    public function updated(Model $model)
    {
        $changes = $this->getLoggableAttributes($model, $model->getChanges());
        $previous = $this->getLoggableAttributes($model, $this->getPreviousAttributes($model));

        if ($changes === [] || $this->isRestoreUpdate($model, $changes, $previous)) {
            return;
        }

        $this->log($model, 'Updated', properties: $this->buildProperties($model, $changes, $previous));
        PreviousAttributesStore::forget($model);
    }

    public function deleted(Model $model)
    {
        if ($this->isForceDeleting($model)) {
            return;
        }

        $this->log($model, 'Deleted', properties: $this->buildProperties($model, $model->getAttributes()));
    }

    public function restored(Model $model)
    {
        $changes = $model->getChanges();
        $previous = $this->getPreviousAttributes($model, true);

        $this->log(
            $model,
            'Restored',
            properties: $this->buildProperties(
                $model,
                $changes !== [] ? $changes : $model->getAttributes(),
                $previous,
            ),
        );
    }

    public function forceDeleted(Model $model)
    {
        $this->log($model, 'Force Deleted', properties: $this->buildProperties($model, $model->getAttributes()));
    }
}
