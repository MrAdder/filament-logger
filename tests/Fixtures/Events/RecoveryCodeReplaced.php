<?php

namespace MrAdder\FilamentLogger\Tests\Fixtures\Events;

use Illuminate\Contracts\Auth\Authenticatable;

class RecoveryCodeReplaced
{
    public function __construct(
        public Authenticatable $user,
    ) {}
}
