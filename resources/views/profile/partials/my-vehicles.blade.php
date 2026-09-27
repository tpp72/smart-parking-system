{{--
    รถของฉัน — ทะเบียนที่ผูกไว้กับบัญชี เพื่อให้รถที่เข้าลานโดยไม่ได้จองผูกกับบัญชีนี้อัตโนมัติ
    การเพิ่มรถทำได้ทางเดียวคือเช็คสถานะรถด้วยรหัสอ้างอิงจากจอทางเข้าลาน (พิสูจน์ว่าอยู่กับรถจริง)
--}}
<section aria-labelledby="vehicles-title" class="rounded-card border border-line bg-surface p-5 shadow-1 sm:p-6">
    <h2 id="vehicles-title" class="text-h3 text-fg">รถของฉัน</h2>
    <p class="mt-1 text-label text-fg-2">
        รถที่ผูกไว้จะผูกกับบัญชีนี้อัตโนมัติเมื่อเข้าลานโดยไม่ได้จองล่วงหน้า — คุณจะได้รับการแจ้งเตือนและเห็นรถในหน้าหลัก
    </p>

    @if ($user->vehicles->isEmpty())
        <p class="mt-4 rounded-control bg-surface-2 px-4 py-5 text-center text-label text-fg-2">
            ยังไม่มีรถที่ผูกไว้
        </p>
    @else
        <ul class="mt-4 divide-y divide-line overflow-hidden rounded-card border border-line">
            @foreach ($user->vehicles as $vehicle)
                <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                    <x-ui.plate :plate="$vehicle->license_plate" :province="$vehicle->plate_province" size="sm" />

                    <form method="POST" action="{{ route('my-vehicles.destroy', $vehicle) }}"
                        data-confirm="ทะเบียน {{ $vehicle->license_plate }} {{ $vehicle->plate_province }} จะไม่ผูกกับบัญชีนี้อีก — รถที่เข้าลานโดยไม่ได้จองจะไม่แจ้งเตือนคุณ และจะไม่เห็นในหน้าหลัก (ประวัติการจอดเดิมยังอยู่ครบ)"
                        data-confirm-title="นำรถออกจากบัญชี?" data-confirm-label="นำออก" data-confirm-tone="danger" data-confirm-cancel="เก็บไว้">
                        @csrf
                        @method('DELETE')
                        <x-ui.button type="submit" variant="secondary" size="sm" class="text-danger">นำออก</x-ui.button>
                    </form>
                </li>
            @endforeach
        </ul>
    @endif

    <p class="mt-4 text-caption text-fg-3">
        เพิ่มรถได้จากหน้า <a href="{{ route('track.show') }}" class="font-semibold text-primary-ink underline underline-offset-4">เช็คสถานะรถ</a>
        โดยใช้รหัสอ้างอิงจากจอที่ทางเข้าลาน — ต้องอยู่กับรถจริงจึงจะผูกได้
    </p>
</section>
