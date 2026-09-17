import './flatpickr-init';

import Alpine from 'alpinejs';
import { initTheme, themeSwitch } from './ui/theme';
import { registerToasts } from './ui/toast';
import { registerConfirm } from './ui/confirm';
import { registerComponents } from './ui/components';
import { initNavigationFeedback } from './ui/navigation';

window.Alpine = Alpine;

initTheme();

Alpine.data('spThemeSwitch', themeSwitch);
registerToasts(Alpine);
registerConfirm(Alpine);      // ดัก submit ของฟอร์ม data-confirm ก่อน (capture phase)
registerComponents(Alpine);

Alpine.start();

initNavigationFeedback();     // แถบโหลดบาง ๆ ตอนเปลี่ยนหน้า + ปุ่ม submit กำลังดำเนินการ
