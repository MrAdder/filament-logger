<?php

namespace MrAdder\FilamentLogger\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Shares the test_records table with TestRecord but is a distinct class.
 *
 * Eloquent observers are attached per class and cannot be detached, so the
 * observer registration tests use this model to keep their listeners from
 * leaking into every other test in the process.
 */
class ObservedTestRecord extends Model
{
    protected $table = 'test_records';

    protected $guarded = [];
}
