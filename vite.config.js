import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],

    server: {
        // ผูก dev server กับ IPv4 loopback ตรงๆ — ป้องกันปัญหา Windows/Chrome ที่ Vite
        // bind แค่ [::1] (IPv6) แล้ว browser ต่อ HMR websocket ไม่สำเร็จ ทำให้หน้าเว็บโหลดค้าง/หมุนตลอด
        host: '127.0.0.1',
        hmr: {
            host: '127.0.0.1',
        },
        watch: {
            // กัน Vite hot-reload วนรอบไม่จบเมื่อ Playwright เขียนไฟล์ผลทดสอบ
            // (screenshot, trace, วิดีโอ) ลงในโปรเจกต์ระหว่าง dev server เปิดอยู่
            ignored: [
                '**/e2e/screenshots/**',
                '**/e2e/reports/**',
                '**/*.webm',        // video recordings
                '**/*.zip',         // trace archives
                '**/*.crdownload',  // Chromium in-progress downloads
            ],
            // Raise the stability threshold so rapid bursts of writes
            // (many screenshots in one test run) don't queue endless reloads.
            stabilityThreshold: 2000,
            pollInterval: 1000,
        },
    },
});
