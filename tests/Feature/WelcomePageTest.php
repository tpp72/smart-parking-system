<?php

namespace Tests\Feature;

use Tests\TestCase;

class WelcomePageTest extends TestCase
{
    public function test_welcome_page_links_to_login_and_register(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Smart Parking')
            ->assertSee(route('login'))
            ->assertSee(route('register'));
    }

    /** คนขับ Walk-in ไม่มีบัญชี ต้องหาทั้งสองหน้าเจอจากหน้าแรก โดยไม่ต้องล็อกอิน */
    public function test_welcome_page_links_to_the_walk_in_pages(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee(route('public.scan.create'))
            ->assertSee('สแกนรถเข้าลาน')
            ->assertSee(route('track.show'))
            ->assertSee('เช็คสถานะรถ');
    }
}
