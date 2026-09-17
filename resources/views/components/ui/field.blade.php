{{-- ชุดช่องกรอก: label + control (slot) + hint + error — control ข้างในอ่าน for/hint ผ่าน @aware เพื่อผูก aria-describedby --}}
@props(['label' => null, 'for' => null, 'hint' => null, 'required' => false, 'error' => null])

@php
    $messages = $error ?? ($for ? ($errors ?? new \Illuminate\Support\ViewErrorBag)->get($for) : []);
@endphp

<div {{ $attributes->merge(['class' => 'flex flex-col gap-1.5']) }}>
    @if ($label)
        <x-ui.label :for="$for" :required="$required">{{ $label }}</x-ui.label>
    @endif

    {{ $slot }}

    @if ($hint)
        <x-ui.hint :id="$for ? $for.'-hint' : null">{{ $hint }}</x-ui.hint>
    @endif

    <x-ui.error :id="$for ? $for.'-error' : null" :messages="$messages" />
</div>
