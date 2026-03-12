<?php

namespace MrAdder\FilamentLogger\Support;

use Illuminate\Support\Facades\Gate;
use Spatie\Activitylog\Models\Activity as ActivityModel;

final class ActivityViewerPrivacy
{
    /**
     * @return array<string, mixed>
     */
    public static function sanitizeProperties(mixed $properties, ?ActivityModel $activity = null): array
    {
        return LogDataSanitizer::sanitizeProperties($properties, [
            'preserve_sensitive_data' => self::canViewSensitiveData($activity),
            'redact_ip_addresses' => self::shouldRedactIpAddresses($activity),
        ]);
    }

    public static function canViewSensitiveData(?ActivityModel $activity = null): bool
    {
        $ability = self::sensitiveAbility();

        if ($ability === null) {
            return false;
        }

        return $activity instanceof ActivityModel
            ? Gate::check($ability, $activity)
            : Gate::check($ability);
    }

    protected static function shouldRedactIpAddresses(?ActivityModel $activity = null): bool
    {
        return config('filament-logger.access.redact_ip_for_unauthorized_viewers', false)
            && ! self::canViewSensitiveData($activity);
    }

    protected static function sensitiveAbility(): ?string
    {
        $ability = config('filament-logger.authorization.sensitive_ability', 'viewSensitiveData');

        return is_string($ability) && filled($ability) ? $ability : null;
    }
}
