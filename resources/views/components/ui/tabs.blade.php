{{--
    Tabs — <x-ui.tabs :tabs="['a' => 'ป้ายแรก', 'b' => 'ป้ายสอง']" label="ชื่อกลุ่มแท็บ">
               <x-ui.tab-panel name="a">…</x-ui.tab-panel>
           </x-ui.tabs>
    คีย์บอร์ด: ← → Home End (roving tabindex)
--}}
@props(['tabs' => [], 'active' => null, 'label' => 'แท็บ'])

@php
    $keys = array_map('strval', array_keys($tabs));
    $active = (string) ($active ?? ($keys[0] ?? ''));
    $baseId = 'tabs-'.\Illuminate\Support\Str::random(6);
@endphp

<div x-data="spTabs({ active: @js($active), keys: @js($keys) })" data-tabs-id="{{ $baseId }}" {{ $attributes }}>
    <div role="tablist" aria-label="{{ $label }}" x-on:keydown="onKeydown($event)"
        class="flex gap-1 overflow-x-auto border-b border-line">
        @foreach ($tabs as $key => $text)
            <button type="button" role="tab"
                id="{{ $baseId }}-tab-{{ $key }}"
                data-tab="{{ $key }}"
                aria-controls="{{ $baseId }}-panel-{{ $key }}"
                aria-selected="{{ (string) $key === $active ? 'true' : 'false' }}"
                tabindex="{{ (string) $key === $active ? 0 : -1 }}"
                x-bind:aria-selected="(active === @js((string) $key)).toString()"
                x-bind:tabindex="active === @js((string) $key) ? 0 : -1"
                x-on:click="select(@js((string) $key))"
                class="-mb-px inline-flex min-h-touch shrink-0 items-center whitespace-nowrap border-b-2 border-transparent px-3 text-body text-fg-2 transition-colors duration-fast hover:text-fg aria-selected:border-primary-ink aria-selected:font-semibold aria-selected:text-fg">
                {{ $text }}
            </button>
        @endforeach
    </div>

    {{ $slot }}
</div>
