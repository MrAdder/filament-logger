<?php

namespace MrAdder\FilamentLogger\Resources;

if (class_exists(\Filament\Schemas\Schema::class)) {
    class ExportPresetResource extends ExportPresetResourceV4 {}
} else {
    class ExportPresetResource extends ExportPresetResourceV3 {}
}
