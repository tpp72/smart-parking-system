{{--
    กราฟแท่งแนวตั้งแบบเรียบ (ไม่พึ่งไลบรารี) — ข้อมูลเดียวกันมีตารางซ่อนสำหรับ screen reader
    <x-ui.bar-chart :data="[['label' => 'ก.ย. 26', 'value' => 1200.0], ...]" caption="รายได้รายเดือน" money />
--}}
@props(['data' => [], 'caption' => '', 'money' => false])

@php
    $max = max(1, (float) collect($data)->max('value'));
    $format = fn ($v) => $money ? \App\Support\Format::baht($v) : number_format((float) $v);
    $lastIndex = count($data) - 1;
@endphp

<figure {{ $attributes->class('w-full') }}>
    <div class="flex h-44 items-end gap-1.5 border-b border-line sm:gap-2" aria-hidden="true">
        @foreach ($data as $i => $point)
            <div class="group relative flex h-full min-w-0 flex-1 flex-col justify-end" title="{{ $point['label'] }}: {{ $format($point['value']) }}">
                <span @class([
                    'block w-full rounded-t-control transition-colors duration-fast',
                    'bg-primary' => $i === $lastIndex,
                    'bg-fg-3/40 group-hover:bg-fg-3/70' => $i !== $lastIndex,
                ]) style="height: {{ max((float) $point['value'] > 0 ? 2 : 0, (float) $point['value'] / $max * 100) }}%"></span>
            </div>
        @endforeach
    </div>
    <div class="mt-1.5 flex gap-1.5 sm:gap-2" aria-hidden="true">
        @foreach ($data as $i => $point)
            <span @class(['min-w-0 flex-1 truncate text-center text-[0.625rem] leading-tight sm:text-caption', 'font-semibold text-fg' => $i === $lastIndex, 'text-fg-3' => $i !== $lastIndex])>{{ $point['label'] }}</span>
        @endforeach
    </div>
    <table class="sr-only">
        <caption>{{ $caption }}</caption>
        <tbody>
            @foreach ($data as $point)
                <tr><th scope="row">{{ $point['label'] }}</th><td>{{ $format($point['value']) }}</td></tr>
            @endforeach
        </tbody>
    </table>
</figure>
