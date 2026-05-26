<?php

namespace MrAdder\FilamentLogger\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Contracts\Auth\Authenticatable as UserContract;
use MrAdder\FilamentLogger\Models\ExportPreset;

class ExportPresetPolicy
{
    use HandlesAuthorization;

    protected function manageAbility(): string
    {
        return config('filament-logger.exports.manage_ability', 'manageExportPresets');
    }

    public function viewAny(UserContract $user): bool
    {
        return $user->can($this->manageAbility());
    }

    public function view(UserContract $user, ExportPreset $preset): bool
    {
        $preset->getKeyName();

        return $user->can($this->manageAbility());
    }

    public function create(UserContract $user): bool
    {
        return $user->can($this->manageAbility());
    }

    public function update(UserContract $user, ExportPreset $preset): bool
    {
        return $this->canMutatePreset($user, $preset, 'update');
    }

    public function delete(UserContract $user, ExportPreset $preset): bool
    {
        return $this->canMutatePreset($user, $preset, 'delete');
    }

    public function restore(UserContract $user, ExportPreset $preset): bool
    {
        return $this->canMutatePreset($user, $preset, 'restore');
    }

    public function forceDelete(UserContract $user, ExportPreset $preset): bool
    {
        return $this->canMutatePreset($user, $preset, 'forceDelete');
    }

    protected function canMutatePreset(UserContract $user, ExportPreset $preset, string $action): bool
    {
        $preset->getKeyName();

        return match ($action) {
            'update', 'delete', 'restore', 'forceDelete' => $user->can($this->manageAbility()),
            default => false,
        };
    }
}
