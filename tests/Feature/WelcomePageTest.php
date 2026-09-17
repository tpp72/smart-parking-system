<?php

namespace Tests\Feature;

use Tests\TestCase;

class WelcomePageTest extends TestCase
{
    public function test_welcome_page_links_to_login_and_register(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Smart Parking System')
            ->assertSee(route('login'))
            ->assertSee(route('register'));
    }
}
