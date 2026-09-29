import './flatpickr-init';

import Alpine from 'alpinejs';
import { initTheme, themeSwitch } from './ui/theme';
import { registerToasts } from './ui/toast';
import { registerConfirm } from './ui/confirm';
import { registerComponents } from './ui/components';
import { initNavigationFeedback } from './ui/navigation';
import { initPlateInputs } from './ui/plate-input';
import { addressSelect } from './ui/address-select';
import { carImage } from './ui/car-image';

window.Alpine = Alpine;

initTheme();

Alpine.data('spThemeSwitch', themeSwitch);
Alpine.data('spAddressSelect', addressSelect);
Alpine.data('spCarImage', carImage);
registerToasts(Alpine);
registerConfirm(Alpine);      // ดัก submit ของฟอร์ม data-confirm ก่อน (capture phase)
registerComponents(Alpine);

Alpine.start();

initNavigationFeedback();     // แถบโหลดบาง ๆ ตอนเปลี่ยนหน้า + ปุ่ม submit กำลังดำเนินการ
initPlateInputs();            // ช่องกรอกทะเบียนแสดงรูปแบบเดียวกับที่ระบบจะเก็บจริง
