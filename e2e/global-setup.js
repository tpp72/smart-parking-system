import { execSync } from 'node:child_process';

/** เตรียมฐานข้อมูลทดสอบใหม่ทุกครั้งก่อนรัน E2E (บัญชีและลานจอดจาก DatabaseSeeder) */
export default function globalSetup() {
  const database = process.env.E2E_DB_DATABASE;

  if (!database || !/test/i.test(database)) {
    throw new Error(`E2E_DB_DATABASE ต้องเป็นฐานข้อมูลทดสอบ (ได้รับ "${database}") — กันการล้างฐานข้อมูลที่ใช้พัฒนา`);
  }

  execSync('php artisan migrate:fresh --seed --force', {
    stdio: 'inherit',
    env: { ...process.env, DB_DATABASE: database },
  });
}
