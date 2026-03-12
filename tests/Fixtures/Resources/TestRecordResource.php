<?php

namespace MrAdder\FilamentLogger\Tests\Fixtures\Resources;

use Filament\Resources\Resource;
use MrAdder\FilamentLogger\Tests\Fixtures\Models\TestRecord;

class TestRecordResource extends Resource
{
    protected static ?string $model = TestRecord::class;
}
