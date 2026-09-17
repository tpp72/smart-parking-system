import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** ค่าสีจาก token (resources/css/tokens.css) พร้อมรองรับ opacity */
const token = (name) => `rgb(var(--color-${name}) / <alpha-value>)`;

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: ['selector', '[data-theme="dark"]'],

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
        './app/Support/**/*.php',
    ],

    theme: {
        extend: {
            colors: {
                page: token('page'),
                surface: { DEFAULT: token('surface'), 2: token('surface-2') },
                line: token('line'),
                field: token('field'),
                fg: { DEFAULT: token('fg'), 2: token('fg-2'), 3: token('fg-3') },
                primary: { DEFAULT: token('primary'), hover: token('primary-hover'), ink: token('primary-ink') },
                'on-primary': token('on-primary'),
                success: token('success'),
                warning: token('warning'),
                danger: token('danger'),
                'on-danger': token('on-danger'),
                scrim: token('scrim'),
            },

            fontFamily: {
                sans: ['"Anuphan Variable"', 'Anuphan', ...defaultTheme.fontFamily.sans],
                mono: ['"Martian Mono Variable"', '"Martian Mono"', ...defaultTheme.fontFamily.mono],
            },

            // สเกลตัวอักษรของ design system (line-height เผื่อสระ/วรรณยุกต์ไทย)
            fontSize: {
                h1: ['1.75rem', { lineHeight: '2.375rem', fontWeight: '700' }],    // 28px
                h2: ['1.375rem', { lineHeight: '1.875rem', fontWeight: '700' }],   // 22px
                h3: ['1.125rem', { lineHeight: '1.625rem', fontWeight: '600' }],   // 18px
                body: ['1rem', { lineHeight: '1.625' }],                             // 16px
                label: ['0.8125rem', { lineHeight: '1.25rem', fontWeight: '600' }], // 13px
                caption: ['0.75rem', { lineHeight: '1.125rem' }],                   // 12px
                kpi: ['2rem', { lineHeight: '2.5rem', fontWeight: '600' }],        // 32px
            },

            // มุม: control/badge/stamp 2px · ปุ่ม/การ์ด 6px (rounded-lg ขึ้นไปถูกจำกัดที่ 6px ตามโลกบัตรจอดรถ)
            borderRadius: {
                sm: 'var(--radius-control)',
                control: 'var(--radius-control)',
                DEFAULT: 'var(--radius-card)',
                md: 'var(--radius-card)',
                lg: 'var(--radius-card)',
                xl: 'var(--radius-card)',
                '2xl': 'var(--radius-card)',
                '3xl': 'var(--radius-card)',
                card: 'var(--radius-card)',
            },

            boxShadow: {
                1: 'var(--shadow-1)',
                overlay: 'var(--shadow-overlay)',
            },

            // ห้าม letter-spacing กับข้อความภาษาไทย — utility tracking ถูกทำให้เป็น 0 กันใช้พลาด
            letterSpacing: {
                wide: '0',
                wider: '0',
                widest: '0',
            },

            minHeight: { touch: '2.75rem' },
            minWidth: { touch: '2.75rem' },
            height: { touch: '2.75rem' },
            width: { touch: '2.75rem' },

            transitionTimingFunction: { out: 'var(--ease-out)' },
            transitionDuration: { fast: '120ms', base: '200ms' },

            zIndex: { toast: '70', modal: '60', drawer: '55', dropdown: '50', progress: '80' },
        },
    },

    plugins: [forms],
};
