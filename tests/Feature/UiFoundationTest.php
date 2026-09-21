<?php

namespace Tests\Feature;

use App\Models\LicensePlateScan;
use App\Models\OwnerResignation;
use App\Models\Payment;
use App\Models\Reservation;
use App\Support\StatusCatalog;
use Illuminate\Support\Js;
use Illuminate\View\ViewException;
use Tests\TestCase;

/** UI Phase 1 — Foundation: status mapping, component, toast, theme, loading */
class UiFoundationTest extends TestCase
{
    public function test_every_system_status_has_a_thai_label_without_english(): void
    {
        $sets = [
            'reservation' => Reservation::STATUSES,
            'payment'     => [Payment::STATUS_UNPAID, Payment::STATUS_PAID, Payment::STATUS_VOID],
            'slot'        => ['available', 'reserved', 'occupied'],
            'scan'        => [LicensePlateScan::RESULT_PASSED, LicensePlateScan::RESULT_LOW_ACCURACY, LicensePlateScan::RESULT_UNREADABLE],
            'review'      => [OwnerResignation::STATUS_PENDING, OwnerResignation::STATUS_APPROVED, OwnerResignation::STATUS_REJECTED],
        ];

        foreach ($sets as $type => $values) {
            $this->assertEqualsCanonicalizing($values, StatusCatalog::values($type), "{$type}: catalog ต้องครบทุกสถานะของระบบ");

            foreach ($values as $value) {
                foreach (StatusCatalog::AUDIENCES as $audience) {
                    $status = StatusCatalog::resolve($type, $value, $audience);
                    $this->assertTrue($status['known']);
                    $this->assertDoesNotMatchRegularExpression('/[A-Za-z]/', $status['label'], "{$type}.{$value} ห้ามมีภาษาอังกฤษ");
                    $this->assertContains($status['tone'], StatusCatalog::TONES);
                    $this->assertContains($status['shape'], StatusCatalog::SHAPES);
                }
            }
        }
    }

    public function test_pending_reservation_wording_depends_on_audience_and_unknown_values_never_leak(): void
    {
        $this->assertSame('รอเจ้าหน้าที่ยืนยันรับเงิน', StatusCatalog::label('reservation', 'pending', 'user'));
        $this->assertSame('รอยืนยันรับมัดจำ', StatusCatalog::label('reservation', 'pending', 'staff'));

        $unknown = StatusCatalog::resolve('reservation', 'on_hold');
        $this->assertFalse($unknown['known']);
        $this->assertSame('ไม่ทราบสถานะ', $unknown['label']);
        $this->assertSame('unknown', $unknown['key']);
    }

    public function test_status_component_renders_thai_label_shape_and_no_raw_value_text(): void
    {
        $this->blade('<x-ui.status type="payment" value="paid" />')
            ->assertSee('ชำระแล้ว')
            ->assertSee('data-status="paid"', false)
            ->assertSee('<svg', false)
            ->assertDontSee('>paid<', false);

        $this->blade('<x-ui.status type="reservation" value="checked_in" audience="user" />')
            ->assertSee('เช็คอินแล้ว')
            ->assertDontSee('>checked_in<', false);
    }

    public function test_icon_only_button_requires_an_accessible_label(): void
    {
        $this->blade('<x-ui.button icon-only label="ปิดหน้าต่าง">x</x-ui.button>')
            ->assertSee('aria-label="ปิดหน้าต่าง"', false);

        try {
            $this->blade('<x-ui.button icon-only>x</x-ui.button>');
            $this->fail('ปุ่มไอคอนอย่างเดียวที่ไม่มี label ต้องถูกปฏิเสธ');
        } catch (ViewException $e) {
            $this->assertStringContainsString('aria-label', $e->getMessage());
        }
    }

    public function test_flash_messages_become_toasts_only_when_enabled(): void
    {
        session()->flash('success', 'บันทึกการชำระเงินแล้ว');

        $expected = Js::from([['tone' => 'success', 'message' => 'บันทึกการชำระเงินแล้ว']])->toHtml();

        $this->blade('<x-ui.toast-region :flash="true" />')->assertSee($expected, false);
        $this->blade('<x-ui.toast-region />')->assertDontSee($expected, false);
    }

    public function test_layout_sets_theme_before_paint_and_has_no_fullscreen_loader(): void
    {
        $response = $this->get(route('login'))->assertOk();

        $response->assertSee("localStorage.getItem('sp-theme')", false)
            ->assertSee('data-theme-preference', false)
            ->assertSee('id="sp-progress"', false)
            ->assertSee('role="radiogroup"', false)
            ->assertDontSee('sp-page-loader', false)
            ->assertDontSee('light-theme', false);
    }

    public function test_foundation_showcase_is_not_available_outside_local(): void
    {
        $this->get('/_ui')->assertNotFound();
    }

    public function test_foundation_showcase_view_renders(): void
    {
        $this->view('dev.ui-foundation')
            ->assertSee('Foundation')
            ->assertSee('รอเจ้าหน้าที่ยืนยันรับเงิน')
            ->assertSee('data-status-type="reservation"', false);
    }

    /** ธีมมืดต้องได้ทั้งตอนมี JavaScript (data-theme) และตอนปิด JavaScript (prefers-color-scheme) ด้วยค่าชุดเดียวกัน */
    public function test_dark_tokens_are_identical_for_attribute_and_system_preference(): void
    {
        $css = file_get_contents(resource_path('css/tokens.css'));

        $declarations = function (string $selector) use ($css): array {
            $start = strpos($css, $selector);
            $this->assertNotFalse($start, "ไม่พบ selector: $selector");
            $body = substr($css, $start, strpos($css, '}', $start) - $start);
            preg_match_all('/(--[a-z0-9-]+|color-scheme)s*:s*([^;]+);/i', $body, $m, PREG_SET_ORDER);

            return collect($m)->mapWithKeys(fn ($d) => [trim($d[1]) => trim($d[2])])->all();
        };

        $byAttribute = $declarations(':root[data-theme="dark"]');
        $byPreference = $declarations(':root:not([data-theme])');

        $this->assertNotEmpty($byAttribute);
        $this->assertSame($byAttribute, $byPreference);
    }

    /** หน้าแจ้งข้อผิดพลาดต้องเป็นภาษาไทยเหมือนหน้าอื่น ไม่ใช่หน้ามาตรฐานภาษาอังกฤษของ Laravel */
    public function test_error_pages_are_thai(): void
    {
        $pages = [
            '401' => 'ต้องเข้าสู่ระบบก่อน',
            '403' => 'ไม่มีสิทธิ์เข้าถึง',
            '404' => 'ไม่พบหน้าที่ต้องการ',
            '419' => 'หน้านี้หมดอายุแล้ว',
            '429' => 'ส่งคำขอถี่เกินไป',
            '500' => 'ระบบขัดข้อง',
            '503' => 'ปิดปรับปรุงชั่วคราว',
        ];

        foreach ($pages as $code => $heading) {
            $html = view("errors.{$code}")->render();

            $this->assertStringContainsString($heading, $html, "หน้า {$code} ต้องมีหัวข้อภาษาไทย");
            $this->assertStringContainsString('lang="th"', $html);
            $this->assertStringContainsString('กลับหน้าแรก', $html);
            $this->assertStringNotContainsString('Not Found', $html);
            $this->assertStringNotContainsString('Server Error', $html);
        }
    }

    /** ผู้ใช้ที่ยังไม่เข้าสู่ระบบต้องเห็นหน้า 404 ภาษาไทย ไม่ใช่หน้ามาตรฐานของ framework */
    public function test_missing_page_renders_the_thai_404(): void
    {
        $this->get('/ไม่มีหน้านี้')
            ->assertNotFound()
            ->assertSee('ไม่พบหน้าที่ต้องการ')
            ->assertDontSee('Not Found');
    }
}
