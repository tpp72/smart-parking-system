{{--
    Sidebar ของ Admin / Owner (lg ขึ้นไป)
    1024–1279px = icon rail (ป้ายชื่อแสดงเมื่อ hover/focus) · ≥1280px = เมนูเต็มพร้อมชื่อกลุ่ม
--}}
<aside class="fixed inset-y-0 left-0 z-30 hidden w-[4.5rem] flex-col border-r border-line bg-surface lg:flex xl:w-64"
    x-data="{ tip: '', tipTop: 0 }">
    <div class="flex h-14 shrink-0 items-center border-b border-line px-3 xl:px-4">
        <div class="w-full xl:hidden">@include('layouts.shell.brand', ['compact' => true])</div>
        <div class="hidden w-full xl:block">@include('layouts.shell.brand')</div>
    </div>

    <nav aria-label="เมนูหลัก" class="min-h-0 flex-1 overflow-y-auto px-2 py-3 xl:px-3"
        x-on:scroll.passive="tip = ''">
        @foreach ($nav['groups'] as $group)
            <div @class(['mt-3 border-t border-line pt-3 xl:mt-5 xl:border-0 xl:pt-0' => ! $loop->first])>
                <h2 id="nav-group-{{ $loop->index }}" class="sr-only mb-1 px-3 text-caption font-medium text-fg-3 xl:not-sr-only">
                    {{ $group['label'] }}
                </h2>
                <ul aria-labelledby="nav-group-{{ $loop->index }}" class="flex flex-col gap-0.5">
                    @foreach ($group['items'] as $item)
                        <li>
                            <a href="{{ $item['href'] }}"
                                @if ($item['active']) aria-current="page" @endif
                                x-on:mouseenter="tip = @js($item['label']); tipTop = $el.getBoundingClientRect().top + $el.offsetHeight / 2"
                                x-on:focus="tip = @js($item['label']); tipTop = $el.getBoundingClientRect().top + $el.offsetHeight / 2"
                                x-on:mouseleave="tip = ''" x-on:blur="tip = ''"
                                @class([
                                    'relative flex min-h-touch items-center justify-center gap-3 rounded-card px-3 text-label transition-colors duration-fast xl:justify-start',
                                    'bg-primary/10 font-semibold text-primary-ink' => $item['active'],
                                    'text-fg-2 hover:bg-surface-2 hover:text-fg' => ! $item['active'],
                                ])>
                                <x-ui.icon :name="$item['icon']" />
                                <span class="sr-only xl:not-sr-only xl:min-w-0 xl:flex-1 xl:truncate">{{ $item['label'] }}</span>
                                <x-ui.count-badge :count="$item['badge']" :label="$item['badgeLabel']"
                                    class="absolute right-1 top-0.5 xl:static" />
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </nav>

    <p class="hidden shrink-0 border-t border-line px-4 py-3 text-caption text-fg-3 xl:block">
        {{ $nav['roleLabel'] }} · {{ $nav['scope'] }}
    </p>

    {{-- ป้ายชื่อเมนูใน icon rail — ใช้ position fixed เพื่อไม่ถูกตัดโดยแถบเลื่อนของเมนู --}}
    <span aria-hidden="true" x-cloak x-show="tip !== ''" x-text="tip"
        x-bind:style="`top: ${tipTop}px`"
        class="pointer-events-none fixed left-[4.75rem] z-dropdown -translate-y-1/2 whitespace-nowrap rounded-control bg-fg px-2 py-1 text-caption text-page xl:hidden"></span>
</aside>
