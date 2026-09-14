<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    /**
     * ย้ายเอกสารแนบคำขอเป็น Owner จาก public disk (เปิดได้ด้วย URL) ไปไว้ใน private disk (project-plan.md §19.3)
     * document_path คงเดิม เปลี่ยนเฉพาะที่เก็บไฟล์
     */
    public function up(): void
    {
        $this->moveDocuments(from: 'public', to: 'local');
    }

    public function down(): void
    {
        $this->moveDocuments(from: 'local', to: 'public');
    }

    private function moveDocuments(string $from, string $to): void
    {
        $source = Storage::disk($from);
        $target = Storage::disk($to);

        DB::table('owner_applications')
            ->whereNotNull('document_path')
            ->orderBy('id')
            ->each(function ($application) use ($source, $target) {
                $path = $application->document_path;

                if ($source->exists($path) && ! $target->exists($path)) {
                    $target->put($path, $source->get($path));
                    $source->delete($path);
                }
            });
    }
};
