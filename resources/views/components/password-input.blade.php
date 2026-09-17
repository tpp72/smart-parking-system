{{-- ช่องรหัสผ่านพร้อมปุ่มแสดง/ซ่อน (ใช้ในหน้า auth) --}}
@props(['id', 'name', 'autocomplete' => 'current-password', 'required' => false, 'invalid' => null])

<div x-data="{ show: false }" class="relative">
    <x-ui.input :id="$id" :name="$name" type="password" x-bind:type="show ? 'text' : 'password'"
        :required="$required" :invalid="$invalid" autocomplete="{{ $autocomplete }}" {{ $attributes->class('pr-12') }} />
    <button type="button"
        x-on:click="show = !show"
        x-bind:aria-label="show ? 'ซ่อนรหัสผ่าน' : 'แสดงรหัสผ่าน'"
        x-bind:aria-pressed="show.toString()"
        aria-label="แสดงรหัสผ่าน"
        aria-controls="{{ $id }}"
        class="absolute inset-y-0 right-0 inline-flex w-touch items-center justify-center rounded-control text-fg-3 transition-colors duration-fast hover:text-fg">
        <svg x-show="!show" class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M1.75 10S4.75 4.5 10 4.5 18.25 10 18.25 10 15.25 15.5 10 15.5 1.75 10 1.75 10z" />
            <circle cx="10" cy="10" r="2.5" />
        </svg>
        <svg x-show="show" x-cloak class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M8.2 4.7A8 8 0 0 1 10 4.5C15.25 4.5 18.25 10 18.25 10a13 13 0 0 1-2.1 2.8M5.3 5.9C3 7.4 1.75 10 1.75 10S4.75 15.5 10 15.5c1.4 0 2.6-.4 3.7-1M2.75 2.75l14.5 14.5" />
            <path d="M8.25 8.3a2.5 2.5 0 0 0 3.45 3.45" />
        </svg>
    </button>
</div>
