<?php

namespace MrAdder\FilamentLogger\Resources\ActivityResource\Pages;

use Filament\Resources\Components\Tab;

class ListActivitiesV3 extends BaseListActivities
{
    public function getHeaderWidgetsColumns(): int|string|array
    {
        return 2;
    }

    protected function makeTab(string $label): Tab
    {
        return Tab::make($label);
    }
}
