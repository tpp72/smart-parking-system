<?php

namespace Tests\Unit\Support;

use App\Support\LicensePlateNormalizer as Plate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** ตัวคั่นมาตรฐานของระบบคือช่องว่าง 1 ตัว · ฐานข้อมูลและหน้าจอใช้ค่าเดียวกัน */
class LicensePlateNormalizerTest extends TestCase
{
    public static function normalCases(): array
    {
        return [
            'ไม่มีตัวคั่น'          => ['กข1234', 'กข 1234'],
            'ยัติภังค์'             => ['กข-1234', 'กข 1234'],
            'ช่องว่างเดียว'         => ['กข 1234', 'กข 1234'],
            'ช่องว่างซ้อน'          => ['กข  1234', 'กข 1234'],
            'ช่องว่างรอบยัติภังค์'  => ['กข - 1234', 'กข 1234'],
            'ยัติภังค์ซ้อน'         => ['กข--1234', 'กข 1234'],
            'ช่องว่างหัวท้าย'       => ['  กข 1234  ', 'กข 1234'],
            'เลขนำหน้าหมวด'         => ['1กข1234', '1กข 1234'],
            'เลขนำหน้า + ยัติภังค์' => ['1กข-1234', '1กข 1234'],
            'เลขนำหน้า + ช่องว่าง'  => ['1กข 1234', '1กข 1234'],
            'เลขหลักเดียว'          => ['กข1', 'กข 1'],
            'จักรยานยนต์ 3 อักษร'   => ['ขคง123', 'ขคง 123'],
            'รถบรรทุก'              => ['43-8633', '43 8633'],
            'รถบรรทุกช่องว่าง'      => ['43 8633', '43 8633'],
            'เลขไทย'                => ['กข๑๒๓๔', 'กข 1234'],
            'ละตินตัวเล็ก'          => ['ab1234', 'AB 1234'],
            'ละตินตัวใหญ่'          => ['AB 1234', 'AB 1234'],
        ];
    }

    #[DataProvider('normalCases')]
    public function test_normalize_produces_the_canonical_space_format(string $input, string $expected): void
    {
        $this->assertSame($expected, Plate::normalize($input));
    }

    /** ทะเบียนลักษณะพิเศษ: ห้ามเดาตำแหน่งตัวคั่น และห้ามทำสระ/วรรณยุกต์เสียรูป */
    public static function specialCases(): array
    {
        return [
            'มีวรรณยุกต์ + ช่องว่าง'   => ['น่ารัก 9999', 'น่ารัก 9999'],
            'มีวรรณยุกต์ ไม่มีตัวคั่น' => ['น่ารัก9999', 'น่ารัก9999'],
            'มีสระ + ช่องว่าง'         => ['ที่รัก 9999', 'ที่รัก 9999'],
            'คำสั้น เลขหลักเดียว'      => ['งาม 1', 'งาม 1'],
            'ยัติภังค์กลายเป็นช่องว่าง' => ['น่ารัก-9999', 'น่ารัก 9999'],
            'ช่องว่างซ้อนในทะเบียนพิเศษ' => ['น่ารัก  9999', 'น่ารัก 9999'],
        ];
    }

    #[DataProvider('specialCases')]
    public function test_special_plates_keep_their_shape(string $input, string $expected): void
    {
        $this->assertSame($expected, Plate::normalize($input));
    }

    public function test_special_plate_is_never_split_by_guessing(): void
    {
        foreach (['น่ารัก9999', 'ที่รัก9999', 'งาม1'] as $plate) {
            $this->assertSame($plate, Plate::normalize($plate), "ระบบไปเดาตัวคั่นให้ $plate");
        }
    }

    public function test_comparison_key_ignores_separators_only(): void
    {
        foreach (['กข1234', 'กข 1234', 'กข-1234', 'กข  1234', 'กข - 1234', 'กข๑๒๓๔'] as $variant) {
            $this->assertSame('กข1234', Plate::comparisonKey($variant), "คีย์ของ $variant ไม่ตรง");
        }

        foreach (['1กข1234', '1กข 1234', '1กข-1234'] as $variant) {
            $this->assertSame('1กข1234', Plate::comparisonKey($variant));
        }

        $this->assertSame('น่ารัก9999', Plate::comparisonKey('น่ารัก 9999'));
        $this->assertSame('น่ารัก9999', Plate::comparisonKey('น่ารัก9999'));
        $this->assertSame(Plate::comparisonKey('ab1234'), Plate::comparisonKey('AB 1234'));
    }

    public function test_different_plates_do_not_share_a_key(): void
    {
        $this->assertNotSame(Plate::comparisonKey('1กข1234'), Plate::comparisonKey('1กข5678'));
        $this->assertNotSame(Plate::comparisonKey('กข1234'), Plate::comparisonKey('1กข1234'));
        $this->assertNotSame(Plate::comparisonKey('น่ารัก9999'), Plate::comparisonKey('นารัก9999'));
    }

    /** สระและวรรณยุกต์ต้องอยู่ครบหลัง normalize (ห้ามลดรูปเพื่อให้สั้นลง) */
    public function test_thai_marks_are_preserved(): void
    {
        foreach (['น่ารัก 9999', 'ที่รัก 9999', 'เมื่อไหร่ 1'] as $plate) {
            $before = preg_match_all('/[\x{0E31}\x{0E34}-\x{0E3A}\x{0E47}-\x{0E4E}]/u', $plate);
            $after = preg_match_all('/[\x{0E31}\x{0E34}-\x{0E3A}\x{0E47}-\x{0E4E}]/u', (string) Plate::normalize($plate));
            $this->assertSame($before, $after, "สระ/วรรณยุกต์ของ $plate หายไป");
        }
    }

    public function test_unicode_is_normalized_to_nfc(): void
    {
        if (! class_exists(\Normalizer::class)) {
            $this->markTestSkipped('ไม่มี Normalizer');
        }

        // ละติน: e + เครื่องหมายเน้นเสียง ต้องรวมเป็นอักขระเดียว
        $this->assertTrue(\Normalizer::isNormalized((string) Plate::normalize("e\u{0301}1234"), \Normalizer::FORM_C));
        // ไทยที่เป็น NFC อยู่แล้วต้องไม่ถูกเปลี่ยน
        $this->assertSame('น่ารัก 9999', Plate::normalize("น\u{0E48}ารัก 9999"));
    }

    public static function edgeCases(): array
    {
        return [
            'null'            => [null, null],
            'ค่าว่าง'         => ['', null],
            'ช่องว่างล้วน'    => ['   ', null],
            'ยัติภังค์ล้วน'   => ['---', null],
            'อักขระเดียว'     => ['ก', 'ก'],
            'เลขล้วน'         => ['1234', '1234'],
            'อีโมจิ'          => ['🚗1234', '🚗1234'],
            'HTML'            => ['<b>กข</b>', '<B>กข</B>'],
            'คล้าย SQL'       => ["กข' OR 1=1", "กข' OR 1=1"],
            'ปนไทยอังกฤษ'     => ['กขAB1234', 'กขAB1234'],
        ];
    }

    #[DataProvider('edgeCases')]
    public function test_edge_cases_never_throw(mixed $input, ?string $expected): void
    {
        $this->assertSame($expected, Plate::normalize($input));
    }

    public function test_very_long_input_is_handled(): void
    {
        $long = str_repeat('ก', 5000).' '.str_repeat('1', 5000);
        $this->assertIsString(Plate::normalize($long));
        $this->assertIsString(Plate::comparisonKey($long));
    }

    /** กันข้อความที่เป็นทะเบียนไม่ได้ โดยไม่ตัดป้ายลักษณะพิเศษทิ้ง */
    public static function plausibilityCases(): array
    {
        return [
            'ทะเบียนปกติ'          => ['กข 1234', true],
            'ไม่มีตัวคั่น'          => ['กข1234', true],
            'เลขนำหน้า'            => ['1กข 1234', true],
            'รถบรรทุก'             => ['43 8633', true],
            'ป้ายพิเศษมีวรรณยุกต์' => ['น่ารัก 9999', true],
            'ป้ายพิเศษคำสั้น'       => ['งาม 1', true],
            'ละติน'                => ['AB 1234', true],
            'เลขล้วน'              => ['1234', true],

            'คำมั่วไม่มีตัวเลข'     => ['ฟ้าพฟฟด', false],
            'อักษรไทยล้วน'         => ['กขคง', false],
            'อังกฤษล้วน'           => ['ABCD', false],
            'อีโมจิ'               => ['🚗 1234', false],
            'HTML'                 => ['<b>1234</b>', false],
            'คล้าย SQL'            => ["กข' OR 1=1", false],
            'ค่าว่าง'              => ['', false],
            'null'                 => [null, false],
            'สั้นเกินไป'           => ['1', false],
            'ยาวเกิน 20'           => ['กขคง 123456789012345678', false],
        ];
    }

    #[DataProvider('plausibilityCases')]
    public function test_plausibility_blocks_junk_but_keeps_special_plates(?string $input, bool $expected): void
    {
        $this->assertSame($expected, Plate::isPlausible($input));
    }

    public function test_recognizable_format_is_advisory_only(): void
    {
        $this->assertTrue(Plate::isRecognizableFormat('กข1234'));
        $this->assertTrue(Plate::isRecognizableFormat('1กข-1234'));
        $this->assertTrue(Plate::isRecognizableFormat('43 8633'));
        $this->assertFalse(Plate::isRecognizableFormat('น่ารัก9999'));
        $this->assertFalse(Plate::isRecognizableFormat(null));

        // แม้ระบบจัดรูปแบบไม่ได้ ก็ยังต้องคืนค่าที่ใช้เก็บได้ ไม่ใช่ null
        $this->assertSame('น่ารัก9999', Plate::normalize('น่ารัก9999'));
    }
}
