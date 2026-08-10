<?php

namespace MrAdder\FilamentLogger\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Activity as BaseActivity;

/**
 * The activity model used by the tenancy recipe in docs/recipes.md.
 *
 * Spatie's model has no tenant column, so tenant-aware installs replace it with
 * one that does. Keeping this fixture in sync with the documented example is
 * what stops the recipe from drifting out of date.
 */
class TenantActivity extends BaseActivity
{
    public function team(): BelongsTo
    {
        return $this->belongsTo(TestTeam::class, 'team_id');
    }
}
