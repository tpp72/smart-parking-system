<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // ไม่ต้อง build frontend (public/build) ก่อนรันเทสต์ — ทั้งบนเครื่องและ CI
        $this->withoutVite();
    }
}
