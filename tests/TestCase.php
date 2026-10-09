<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\Request;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        // env を production に差し替えたテストで TrustHosts が設定する静的状態を、次のテストへ持ち越さない。
        Request::setTrustedHosts([]);

        parent::tearDown();
    }
}
