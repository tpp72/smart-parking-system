{{-- เมนูบัญชี (desktop): โปรไฟล์ + รายการตามบทบาท + ออกจากระบบ --}}
<x-ui.dropdown align="right" width="64">
    <x-slot name="trigger">
        <button type="button"
            class="inline-flex min-h-touch items-center gap-2.5 rounded-card px-2 text-start text-fg transition-colors duration-fast hover:bg-surface-2">
            <span aria-hidden="true"
                class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-control border border-line bg-surface-2 text-label font-semibold text-fg-2">
                {{ mb_strtoupper(mb_substr($nav['name'], 0, 1)) }}
            </span>
            <span class="flex min-w-0 flex-col leading-tight">
                <span class="max-w-[10rem] truncate text-label font-semibold">{{ $nav['name'] }}</span>
                <span class="text-caption text-fg-3">{{ $nav['roleLabel'] }}</span>
            </span>
            <x-ui.icon name="chevron-down" class="h-4 w-4 text-fg-3" />
            <span class="sr-only">เปิดเมนูบัญชี</span>
        </button>
    </x-slot>

    <x-slot name="content">
        <div class="border-b border-line px-3 pb-2 pt-1">
            <p class="truncate text-label font-semibold text-fg">{{ $nav['name'] }}</p>
            <p class="truncate text-caption text-fg-3">{{ $nav['email'] }}</p>
        </div>
        <div class="py-1">
            @foreach ($nav['account'] as $item)
                @continue(! $nav['staff'] && in_array($item['key'], ['history', 'scan'], true))
                <x-ui.dropdown-item :href="$item['href']" :aria-current="$item['active'] ? 'page' : null">
                    <x-ui.icon :name="$item['icon']" class="h-5 w-5 text-fg-3" />
                    {{ $item['label'] }}
                </x-ui.dropdown-item>
            @endforeach
        </div>
        <div class="border-t border-line py-1">
            <form method="POST" action="{{ route('logout') }}" data-no-busy>
                @csrf
                <x-ui.dropdown-item type="submit">
                    <x-ui.icon name="logout" class="h-5 w-5 text-fg-3" />
                    ออกจากระบบ
                </x-ui.dropdown-item>
            </form>
        </div>
    </x-slot>
</x-ui.dropdown>
