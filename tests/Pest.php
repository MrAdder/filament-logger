<?php

use MrAdder\FilamentLogger\Tests\TestCase;

// Laravel's shipped database config still touches deprecated PDO mysql constants on PHP 8.4
// during Testbench boot, even though this package uses sqlite in tests.
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

uses(TestCase::class)->in(__DIR__);
