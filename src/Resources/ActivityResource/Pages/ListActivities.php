<?php

namespace MrAdder\FilamentLogger\Resources\ActivityResource\Pages;

use Filament\Schemas\Components\Tabs\Tab;

class ListActivities extends BaseListActivities
{
    public function getHeaderWidgetsColumns(): int | array
    {
        return 2;
    }

    protected function makeTab(string $label): Tab
    {
        return Tab::make($label);
    }
}
