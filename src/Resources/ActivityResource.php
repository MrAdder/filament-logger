<?php

namespace MrAdder\FilamentLogger\Resources;

if (class_exists(\Filament\Schemas\Schema::class)) {
    class ActivityResource extends ActivityResourceV4 {}
} else {
    class ActivityResource extends ActivityResourceV3 {}
}
