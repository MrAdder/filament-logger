<?php

namespace MrAdder\FilamentLogger\Tests\Fixtures\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class TestUser extends Authenticatable
{
    protected $table = 'users';

    protected $guarded = [];
}
