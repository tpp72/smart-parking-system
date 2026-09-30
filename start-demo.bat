@echo off
REM ===================================================================
REM  เปิดระบบ Smart Parking สำหรับ Demo ผ่าน Cloudflare Quick Tunnel
REM  ดับเบิลคลิกไฟล์นี้ได้เลย หรือรันจาก cmd
REM ===================================================================

set PHP="C:\Users\nongt\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.4_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe"
set CLOUDFLARED="C:\Program Files (x86)\cloudflared\cloudflared.exe"

cd /d E:\smart-parking-system

echo.
echo [1/3] เปิด Laravel (php artisan serve)...
start "Laravel Serve" cmd /k %PHP% artisan serve --host=127.0.0.1 --port=8000

timeout /t 2 >nul

echo [2/3] เปิด Cloudflare Quick Tunnel...
start "Cloudflare Tunnel" cmd /k %CLOUDFLARED% tunnel --url http://127.0.0.1:8000

timeout /t 2 >nul

echo [3/3] เปิด Scheduler (reservations:expire ทุกนาที)...
start "Scheduler" cmd /k %PHP% artisan schedule:work

echo.
echo ============================================================
echo  เปิดครบ 3 หน้าต่างแล้ว
echo.
echo  ขั้นตอนต่อไป (ต้องทำเองทุกครั้ง):
echo  1. ไปที่หน้าต่าง "Cloudflare Tunnel" คัดลอกลิงก์ https://xxxxx.trycloudflare.com
echo  2. เปิดไฟล์ .env แก้บรรทัด APP_URL= ให้เป็นลิงก์ที่ได้
echo  3. (ถ้าแก้ resources/views หรือ resources/css/js มา) รัน: npm run build
echo  4. เปิดลิงก์ทดสอบเองก่อนแชร์ให้คนอื่น
echo ============================================================
echo.
pause
