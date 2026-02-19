<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        // Additional test setup
    }

    protected function tearDown(): void
    {
        // Cleanup tenant context after each test
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        parent::tearDown();
    }
}