<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** ไอคอนของระบบ: บนเว็บใช้ SVG · ในอีเมลต้องเป็นไฟล์ PNG ที่แนบไปกับอีเมล ไม่ใช่ลิงก์ URL */
class BrandIconTest extends TestCase
{
    use RefreshDatabase;

    public function test_icon_files_exist_and_pages_point_at_them(): void
    {
        // ค่าต้องไม่ว่าง — .env.example ประกาศ BRAND_LOGO / BRAND_LOGO_EMAIL ไว้เป็นค่าว่าง (CI ใช้ไฟล์นี้)
        // env() คืนสตริงว่างไม่ใช่ null ค่า default ของ env() จึงไม่ถูกใช้ ต้องถอยด้วย ?: แทน
        // ถ้าไม่เช็คตรงนี้ public_path('') จะชี้ไปที่โฟลเดอร์ public ซึ่ง assertFileExists ผ่านไปเฉย ๆ
        $this->assertNotSame('', config('brand.logo'), 'brand.logo ว่าง — ค่า default ไม่ถูกใช้');
        $this->assertNotSame('', config('brand.logo_email'), 'brand.logo_email ว่าง — ค่า default ไม่ถูกใช้');

        $this->assertFileExists(public_path('favicon.svg'));
        $this->assertFileExists(public_path('favicon.ico'));
        $this->assertFileExists(public_path(config('brand.logo_email')));
        $this->assertFileExists(public_path(config('brand.logo')));

        // ทุกหน้าที่มี <head> ของตัวเองต้องประกาศไอคอนครบ
        foreach (['/', '/marketplace', '/login'] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertStringContainsString('favicon.svg', $html, "ไม่พบ favicon.svg ใน $url");
            $this->assertStringContainsString('favicon.ico', $html, "ไม่พบ favicon.ico ใน $url");
        }
    }

    /**
     * ผู้รับเปิดอีเมลจากเครื่องอื่น และ Gmail/Outlook ดึงรูปผ่านเซิร์ฟเวอร์ของตัวเอง
     * ถ้าปล่อยเป็น URL ของเว็บ (localhost หรือหลัง firewall) ผู้รับจะเห็นรูปเสีย
     */
    public function test_brand_icon_is_embedded_in_sent_mail_not_linked_by_url(): void
    {
        config(['mail.default' => 'array']);
        Mail::purge('array');

        $user = User::factory()->create();
        $user->notify(new ResetPassword('token-for-test'));

        $messages = app('mailer')->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);

        $email = $messages[0]->getOriginalMessage();
        $html = $email->getHtmlBody();

        // อ้างถึงไฟล์ที่แนบมา ไม่ใช่ URL ของเว็บ
        $this->assertStringContainsString('src="cid:', $html);
        $this->assertStringNotContainsString('<img src="'.url(config('brand.logo_email')), $html);

        // ต้องมีไฟล์ภาพแนบมาจริง และเป็น PNG (อีเมลแสดง SVG ไม่ได้)
        $attachments = $email->getAttachments();
        $this->assertNotEmpty($attachments, 'ไม่มีไฟล์ไอคอนแนบมากับอีเมล');
        $this->assertSame('image/png', $attachments[0]->getMediaType().'/'.$attachments[0]->getMediaSubtype());

        // cid ที่อ้างในเนื้ออีเมลต้องตรงกับ Content-ID ของไฟล์แนบหลัง Symfony ประกอบข้อความเสร็จ
        $raw = quoted_printable_decode($messages[0]->toString());
        $this->assertSame(1, preg_match('/src="cid:([^"]+)"/i', $raw, $src));
        $this->assertStringContainsString('Content-ID: <'.$src[1].'>', $messages[0]->toString());

        $this->assertStringNotContainsString('.svg', $html);
    }
}
