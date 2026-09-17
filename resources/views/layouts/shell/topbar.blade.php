{{--
    แถบบน — Staff: ตำแหน่งปัจจุบัน + แจ้งเตือน/ธีม/บัญชี · User: ชื่อระบบ + เมนูหลัก (desktop) + ปุ่มจองที่จอด
    มือถือ (< lg) เหลือชื่อระบบ + แจ้งเตือน (Staff) + ธีม — เมนูย้ายไปแถบล่าง
--}}
<header class="sticky top-0 z-20 border-b border-line bg-surface">
    <div @class([
        'flex h-14 items-center gap-2 px-4 sm:px-6',
        'mx-auto max-w-7xl lg:px-8' => ! $nav['staff'],
    ])>
        <div @class(['min-w-0', 'lg:hidden' => $nav['staff']])>
            @include('layouts.shell.brand')
        </div>

        @if ($nav['staff'])
            <nav aria-label="ตำแหน่งปัจจุบัน" class="hidden min-w-0 lg:block">
                <ol class="flex items-center gap-1.5 text-label">
                    <li class="text-fg-3">{{ $nav['roleLabel'] }}</li>
                    @if (($nav['current']['group'] ?? null) && $nav['current']['group'] !== $nav['current']['label'])
                        <li class="flex items-center gap-1.5 text-fg-3">
                            <x-ui.icon name="chevron-right" class="h-4 w-4" />{{ $nav['current']['group'] }}
                        </li>
                    @endif
                    @if ($nav['current'])
                        <li class="flex min-w-0 items-center gap-1.5 font-semibold text-fg" aria-current="page">
                            <x-ui.icon name="chevron-right" class="h-4 w-4 text-fg-3" />
                            <span class="truncate">{{ $nav['current']['label'] }}</span>
                        </li>
                    @endif
                </ol>
            </nav>
        @else
            <nav aria-label="เมนูหลัก" class="ml-4 hidden self-stretch lg:flex">
                @foreach ($nav['top'] as $item)
                    <a href="{{ $item['href'] }}" @if ($item['active']) aria-current="page" @endif
                        @class([
                            'relative inline-flex h-14 items-center gap-2 px-3 text-label transition-colors duration-fast',
                            'font-semibold text-fg shadow-[inset_0_-2px_0_rgb(var(--color-primary-ink))]' => $item['active'],
                            'text-fg-2 hover:bg-surface-2 hover:text-fg' => ! $item['active'],
                        ])>
                        <x-ui.icon :name="$item['icon']" class="h-5 w-5 {{ $item['active'] ? 'text-primary-ink' : 'text-fg-3' }}" />
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </nav>
        @endif

        <div class="ml-auto flex items-center gap-1.5">
            @if ($nav['limited'])
                <p class="hidden text-label text-fg-3 sm:block">{{ $nav['limitedReason'] }}</p>
            @endif

            @if ($nav['primary'] ?? null)
                <x-ui.button :href="$nav['primary']['href']" size="sm" class="mr-2 hidden lg:inline-flex">
                    <x-ui.icon name="plus" class="h-4 w-4" />
                    {{ $nav['primary']['label'] }}
                </x-ui.button>
            @endif

            @if ($bell = $nav['notifications'])
            <a href="{{ $bell['href'] }}" @if ($bell['active']) aria-current="page" @endif
                @class([
                    'relative inline-flex min-h-touch min-w-touch items-center justify-center rounded-card transition-colors duration-fast',
                    'hidden lg:inline-flex' => ! $nav['staff'],
                    'bg-primary/10 text-primary-ink' => $bell['active'],
                    'text-fg-2 hover:bg-surface-2 hover:text-fg' => ! $bell['active'],
                ])>
                <x-ui.icon name="bell" />
                <span class="sr-only">{{ $bell['label'] }}</span>
                <x-ui.count-badge :count="$bell['badge']" :label="$bell['badgeLabel']" class="absolute right-1 top-1" />
            </a>
            @endif

            <div @class(['hidden lg:block' => ! $nav['limited']])><x-ui.theme-switch popover /></div>

            <div @class(['hidden lg:block' => ! $nav['limited']])>
                @include('layouts.shell.account-menu')
            </div>
        </div>
    </div>
</header>
