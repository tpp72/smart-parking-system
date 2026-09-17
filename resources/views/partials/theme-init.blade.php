{{-- ตั้งธีมก่อน paint (กันแสงวาบธีมผิด) — ค่าที่จำไว้: light | dark | system (ค่าเริ่มต้น = ตาม OS) · ต้องตรงกับ resources/js/ui/theme.js --}}
<meta name="color-scheme" content="light dark">
<script>
    (function () {
        var preference = 'system';
        try { preference = localStorage.getItem('sp-theme') || 'system'; } catch (e) {}
        if (preference !== 'light' && preference !== 'dark') preference = 'system';
        var dark = preference === 'dark' || (preference === 'system' && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
        var root = document.documentElement;
        root.setAttribute('data-theme', dark ? 'dark' : 'light');
        root.setAttribute('data-theme-preference', preference);
    })();
</script>
