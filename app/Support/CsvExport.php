<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV Export ของ Admin (project-plan.md §17.5) — ใช้ร่วมกันทุกประเภทข้อมูล
 * UTF-8 BOM (Excel อ่านภาษาไทยได้) · ดึงข้อมูลทีละ 1,000 แถว · กัน CSV/Formula Injection
 */
final class CsvExport
{
    /**
     * @param string                    $basename ชื่อไฟล์ (ต่อท้ายด้วยวันเวลาที่ Export)
     * @param array<int, string>        $header   หัวคอลัมน์
     * @param Builder                   $query    ต้องมี orderBy เพื่อให้แบ่งหน้าได้ถูกต้อง
     * @param callable(object): array   $map      แปลง 1 แถวของ query เป็นค่าตามลำดับหัวคอลัมน์
     */
    public static function download(string $basename, array $header, Builder $query, callable $map): StreamedResponse
    {
        $filename = $basename . '_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($header, $query, $map) {
            $out = fopen('php://output', 'w');

            // UTF-8 BOM กัน Excel อ่านภาษาไทยเพี้ยน
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $header, ',', '"', '');

            $query->chunk(1000, function ($rows) use ($out, $map) {
                foreach ($rows as $row) {
                    fputcsv($out, array_map([self::class, 'cell'], $map($row)), ',', '"', '');
                }
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** ค่าที่ขึ้นต้นด้วย = + - @ ถูก Excel ตีความเป็นสูตร — เติม ' นำหน้าเพื่อให้เป็นข้อความ */
    public static function cell(mixed $value): mixed
    {
        if (is_string($value) && !is_numeric($value) && preg_match('/^[=+\-@\t\r]/', $value)) {
            return "'" . $value;
        }

        return $value;
    }
}
