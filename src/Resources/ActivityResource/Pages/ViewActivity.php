<?php

namespace MrAdder\FilamentLogger\Resources\ActivityResource\Pages;

use Filament\Resources\Pages\ViewRecord;

class ViewActivity extends ViewRecord
{
    public static function getResource(): string
    {
        return config('filament-logger.activity_resource');
    }
}
