<?php

namespace MrAdder\FilamentLogger\Resources;

use Filament\Schemas\Schema;

if (class_exists(Schema::class)) {
    class ExportPresetResource extends ExportPresetResourceV4 {}
} else {
    class ExportPresetResource extends ExportPresetResourceV3 {}
}
