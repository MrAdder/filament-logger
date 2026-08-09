<?php

namespace MrAdder\FilamentLogger\Resources;

use Filament\Schemas\Schema;

if (class_exists(Schema::class)) {
    class ActivityResource extends ActivityResourceV4 {}
} else {
    class ActivityResource extends ActivityResourceV3 {}
}
