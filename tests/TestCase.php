<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Mockery;
use Override;

abstract class TestCase extends BaseTestCase
{
    protected int $timestamp;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->timestamp = time();
    }

    #[Override]
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
