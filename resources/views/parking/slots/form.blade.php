{{--
    เพิ่ม / แก้ไขช่องจอดทีละช่อง (ใช้ร่วม Owner และ Admin) — $slot = null คือเพิ่มใหม่
    สถานะช่องระบบเป็นผู้จัดการ · ช่องที่ถูกจองหรือมีรถจอดอยู่ย้ายไปลานอื่นไม่ได้
--}}
@use('App\Support\StatusCatalog')

@php
    $isEdit = $slot !== null;
    $lotValue = old('parking_lot_id', $slot?->parking_lot_id ?? ($selectedLotId ?: $lots->first()?->id));
    $backLot = $slot?->parking_lot_id ?? ($selectedLotId ?: null);
@endphp

<x-app-layout>
    <div class="mx-auto max-w-2xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        <a href="{{ route("{$scope}.parking-slots.index", array_filter(['lot_id' => $backLot])) }}" class="inline-flex min-h-touch items-center gap-1 text-label font-semibold text-primary-ink underline-offset-4 hover:underline">
            <x-ui.icon name="chevron-right" class="h-4 w-4 rotate-180" /> ช่องจอด
        </a>
        <h1 class="mt-2 text-h1 text-fg">{{ $isEdit ? 'แก้ไขช่องจอด '.$slot->slot_number : 'เพิ่มช่องจอด' }}</h1>
        <p class="mt-1 text-fg-2">ช่องใหม่มีสถานะ "ว่าง" เสมอ · สถานะเปลี่ยนตามการจองและรถเข้า-ออกโดยระบบ</p>

        @if ($errors->any())
            <x-ui.alert tone="danger" class="mt-6">{{ $errors->first() }}</x-ui.alert>
        @endif

        <form method="POST" action="{{ $isEdit ? route("{$scope}.parking-slots.update", $slot) : route("{$scope}.parking-slots.store") }}"
            class="mt-6 rounded-card border border-line bg-surface p-5 shadow-1 sm:p-6">
            @csrf
            @if ($isEdit) @method('PUT') @endif

            <div class="flex flex-col gap-5">
                <x-ui.field label="ลานจอด" for="parking_lot_id" required
                    :hint="$isEdit && $slot->status !== 'available' ? 'ช่องนี้สถานะ '.StatusCatalog::label('slot', $slot->status).' จึงย้ายไปลานอื่นไม่ได้' : null">
                    <x-ui.select id="parking_lot_id" name="parking_lot_id" required>
                        @foreach ($lots as $lot)
                            <option value="{{ $lot->id }}" @selected((string) $lotValue === (string) $lot->id)>{{ $lot->name }}</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>

                <x-ui.field label="เลขช่อง" for="slot_number" required hint="ไม่ซ้ำกับช่องอื่นในลานเดียวกัน · ใช้อักษรนำหน้าเพื่อจัดเป็นแถวในแผนผัง เช่น A001">
                    <x-ui.input id="slot_number" name="slot_number" :value="old('slot_number', $slot?->slot_number)" required maxlength="255" autocomplete="off" numeric />
                </x-ui.field>

                @if ($isEdit)
                    <p class="text-label text-fg-2">สถานะปัจจุบัน: <x-ui.status type="slot" :value="$slot->status" size="sm" /></p>
                @endif
            </div>

            <div class="mt-6 flex flex-wrap gap-3 border-t border-line pt-5">
                <x-ui.button type="submit">{{ $isEdit ? 'บันทึกการแก้ไข' : 'เพิ่มช่องจอด' }}</x-ui.button>
                <x-ui.button variant="ghost" :href="route($scope.'.parking-slots.index', array_filter(['lot_id' => $backLot]))">ยกเลิก</x-ui.button>
            </div>
        </form>
    </div>
</x-app-layout>
