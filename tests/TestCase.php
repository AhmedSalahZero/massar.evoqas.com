<?php

namespace Tests;

use App\Support\Permissions;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

// ══════════════════════════════════════════════════════════════════
//  Massar — Base Test Case
//  Location: tests/TestCase.php
//
//  Every automated test extends this. Run them all with:
//      php artisan test
//  They use a temporary in-memory database (see phpunit.xml), so your
//  real data is never touched and no extra database is needed.
//
//  withoutVite(): tests check the server's answers, not the compiled
//  screens, so they do not need `npm run build` first.
//  Permissions::flush(): the permission checker remembers answers for
//  speed; clearing it keeps one test from affecting the next.
// ══════════════════════════════════════════════════════════════════

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Permissions::flush();
    }
}
