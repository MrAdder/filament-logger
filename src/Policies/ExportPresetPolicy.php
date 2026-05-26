<?php

namespace MrAdder\FilamentLogger\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Contracts\Auth\Authenticatable as UserContract;

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

    public function view(UserContract $user, mixed ...$unused): bool
    {
        return $user->can($this->manageAbility());
    }

    public function create(UserContract $user): bool
    {
        return $user->can($this->manageAbility());
    }

    public function update(UserContract $user, mixed ...$unused): bool
    {
        return $user->can($this->manageAbility());
    }

    public function delete(UserContract $user, mixed ...$unused): bool
    {
        return $user->can($this->manageAbility());
    }

    public function restore(UserContract $user, mixed ...$unused): bool
    {
        return $user->can($this->manageAbility());
    }

    public function forceDelete(UserContract $user, mixed ...$unused): bool
    {
        return $user->can($this->manageAbility());
    }
}
