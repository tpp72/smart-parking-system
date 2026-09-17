{{--
    แถบแนวนอนเปรียบเทียบค่า (ไม่พึ่งไลบรารี) — ป้าย + แถบ + ตัวเลขกำกับเสมอ ไม่สื่อด้วยสีหรือความยาวอย่างเดียว
    <x-ui.bar-list caption="การจองตามสถานะ" unit="รายการ" :items="[
        ['label' => 'ยืนยันแล้ว', 'value' => 4, 'tone' => 'info'],
        ['label' => 'ลาน A', 'value' => 9, 'href' => '...', 'current' => true],
    ]" />
    tone: neutral | info | success | warning | danger (ค่าเริ่มต้น neutral) · current = เน้นแถวที่กำลังดู
--}}
@props(['items' => [], 'caption' => '', 'unit' => ''])

@php
    $max = max(1, (int) collect($items)->max('value'));
    $tones = [
        'neutral' => 'bg-fg-3/50',
        'info'    => 'bg-primary',
        'success' => 'bg-success',
        'warning' => 'bg-warning',
        'danger'  => 'bg-danger',
    ];
@endphp

<figure {{ $attributes->class('w-full') }}>
    @if ($caption !== '')
        <figcaption class="sr-only">{{ $caption }}</figcaption>
    @endif
    <ul class="flex flex-col gap-1">
        @foreach ($items as $item)
            @php
                $value = (int) ($item['value'] ?? 0);
                $current = (bool) ($item['current'] ?? false);
                $href = $item['href'] ?? null;
                $bar = $current ? 'bg-primary' : ($tones[$item['tone'] ?? 'neutral'] ?? $tones['neutral']);
                $rowClass = 'grid min-h-touch grid-cols-[minmax(0,7.5rem)_minmax(0,1fr)_auto] items-center gap-3 rounded-control px-2 sm:grid-cols-[minmax(0,10rem)_minmax(0,1fr)_auto]';
            @endphp
            <li>
                @if ($href)
                    <a href="{{ $href }}" @if ($current) aria-current="true" @endif
                        @class([$rowClass, 'transition-colors duration-fast hover:bg-surface-2', 'bg-primary/5' => $current])>
                @else
                    <div class="{{ $rowClass }}">
                @endif
                        <span @class(['truncate text-label', 'font-semibold text-fg' => $current || $value > 0, 'font-normal text-fg-2' => ! $current && $value === 0])>{{ $item['label'] }}</span>
                        <span class="h-2.5 overflow-hidden rounded-full bg-line" aria-hidden="true">
                            <span class="block h-full rounded-full {{ $bar }}" style="width: {{ $value > 0 ? max(2, round($value / $max * 100, 2)) : 0 }}%"></span>
                        </span>
                        <span @class(['num text-label', 'text-fg' => $value > 0, 'text-fg-3' => $value === 0])>
                            {{ number_format($value) }}<span class="sr-only"> {{ $unit }}</span>
                        </span>
                @if ($href)
                    </a>
                @else
                    </div>
                @endif
            </li>
        @endforeach
    </ul>
</figure>
