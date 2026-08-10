<?php

namespace MrAdder\FilamentLogger\Tests\Fixtures\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class TestUser extends Authenticatable
{
    // Real application user models carry this; export notifications rely on it.
    use Notifiable;

    protected $table = 'users';

    protected $guarded = [];
}
