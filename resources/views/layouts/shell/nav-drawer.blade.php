{{--
    Drawer เมนูบนมือถือ — Staff: "เมนู" ครบทุกกลุ่ม · User: "บัญชี" (โปรไฟล์ / ประวัติการจอด / AI สแกน / เจ้าของลาน)
    รวมธีมและออกจากระบบไว้ท้าย
--}}
@php
    $link = fn (array $item) => \Illuminate\Support\Arr::toCssClasses([
        'flex min-h-touch items-center gap-3 rounded-card px-3 text-body transition-colors duration-fast',
        'bg-primary/10 font-semibold text-primary-ink' => $item['active'],
        'text-fg hover:bg-surface-2' => ! $item['active'],
    ]);
@endphp

<x-ui.drawer name="sp-nav" :title="$nav['staff'] ? 'เมนู' : 'บัญชี'">
    <div class="flex items-center gap-3 border-b border-line px-4 py-4">
        <span aria-hidden="true"
            class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-control border border-line bg-surface-2 text-body font-semibold text-fg-2">
            {{ mb_strtoupper(mb_substr($nav['name'], 0, 1)) }}
        </span>
        <div class="min-w-0">
            <p class="truncate text-body font-semibold text-fg">{{ $nav['name'] }}</p>
            <p class="truncate text-caption text-fg-3">
                {{ $nav['roleLabel'] }}@if ($nav['staff']) · {{ $nav['scope'] }}@endif
            </p>
        </div>
    </div>

    @if ($nav['staff'])
        <nav aria-label="เมนูทั้งหมด" class="px-2 py-2">
            @foreach ($nav['groups'] as $group)
                <h3 id="drawer-group-{{ $loop->index }}" class="px-3 pb-1 pt-3 text-caption font-medium text-fg-3">{{ $group['label'] }}</h3>
                <ul aria-labelledby="drawer-group-{{ $loop->index }}" class="flex flex-col gap-0.5">
                    @foreach ($group['items'] as $item)
                        <li>
                            <a href="{{ $item['href'] }}" class="{{ $link($item) }}" @if ($item['active']) aria-current="page" @endif>
                                <x-ui.icon :name="$item['icon']" class="h-5 w-5 {{ $item['active'] ? '' : 'text-fg-3' }}" />
                                <span class="min-w-0 flex-1 truncate">{{ $item['label'] }}</span>
                                <x-ui.count-badge :count="$item['badge']" :label="$item['badgeLabel']" />
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endforeach
        </nav>
    @endif

    <nav aria-label="บัญชี" @class(['px-2 py-2', 'border-t border-line' => $nav['staff']])>
        @if ($nav['staff'])
            <h3 class="px-3 pb-1 pt-3 text-caption font-medium text-fg-3">บัญชี</h3>
        @endif
        <ul class="flex flex-col gap-0.5">
            @foreach ($nav['account'] as $item)
                <li>
                    <a href="{{ $item['href'] }}" class="{{ $link($item) }}" @if ($item['active']) aria-current="page" @endif>
                        <x-ui.icon :name="$item['icon']" class="h-5 w-5 {{ $item['active'] ? '' : 'text-fg-3' }}" />
                        <span class="min-w-0 flex-1 truncate">{{ $item['label'] }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>

    <div class="border-t border-line px-4 py-4">
        <p id="drawer-theme-label" class="mb-2 text-caption font-medium text-fg-3">ธีมการแสดงผล</p>
        <x-ui.theme-switch class="w-full [&>button]:flex-1" />
    </div>

    <div class="border-t border-line px-2 py-2">
        <form method="POST" action="{{ route('logout') }}" data-no-busy>
            @csrf
            <button type="submit" class="{{ $link(['active' => false]) }} w-full">
                <x-ui.icon name="logout" class="h-5 w-5 text-fg-3" />
                ออกจากระบบ
            </button>
        </form>
    </div>
</x-ui.drawer>
