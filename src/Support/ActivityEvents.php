<?php

namespace MrAdder\FilamentLogger\Support;

final class ActivityEvents
{
    public const FORCE_DELETED = 'Force Deleted';

    public const FAILED_LOGIN = 'Failed Login';

    private function __construct()
    {
        // This class only exposes shared event-name constants and should not be instantiated.
    }
}
