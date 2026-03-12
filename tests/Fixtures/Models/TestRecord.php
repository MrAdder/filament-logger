<?php

namespace MrAdder\FilamentLogger\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TestRecord extends Model
{
    use SoftDeletes;

    protected $table = 'test_records';

    protected $guarded = [];
}
