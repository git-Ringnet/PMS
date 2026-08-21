<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    // Shared models use SYSTEM_DB_CONNECTION=sqlite during automated tests.
}
