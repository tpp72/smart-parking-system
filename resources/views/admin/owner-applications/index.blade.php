{{--
    คำขอเป็นเจ้าของลาน — แท็บสถานะพร้อมจำนวน · ค้นหา · รายการเรียงรอพิจารณาก่อน · ไปหน้ารายละเอียดเพื่อพิจารณา
    ต้องการ: $applications, $q, $status ('' | pending | approved | rejected), $counts
--}}
@use('App\Support\Format')

@php
    $tabs = [
        '' => ['ทั้งหมด', array_sum($counts)],
        'pending' => ['รอพิจารณา', $counts['pending']],
        'approved' => ['อนุมัติแล้ว', $counts['approved']],
        'rejected' => ['ไม่อนุมัติ', $counts['rejected']],
    ];
@endphp

<x-app-layout flash-toast>
    <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        <div class="mb-6">
            <h1 class="text-h1 text-fg">คำขอเป็นเจ้าของลาน</h1>
            <p class="mt-1 text-fg-2">ผู้ใช้ที่ต้องการเปิดลานจอดในระบบ · อนุมัติแล้วบัญชีเป็นเจ้าของลานและเพิ่มลานเองได้</p>
        </div>

        @if ($errors->any())
            <x-ui.alert tone="danger" class="mb-4">{{ $errors->first() }}</x-ui.alert>
        @endif

        <nav aria-label="กรองตามสถานะ" class="mb-4 flex gap-1 overflow-x-auto border-b border-line">
            @foreach ($tabs as $key => [$label, $count])
                <a href="{{ route('admin.owner-applications.index', array_filter(['status' => $key, 'q' => $q])) }}" @if ($status === $key) aria-current="page" @endif
                    @class([
                        'inline-flex min-h-touch shrink-0 items-center gap-1.5 px-3 text-label transition-colors duration-fast',
                        'font-semibold text-fg shadow-[inset_0_-2px_0_rgb(var(--color-primary-ink))]' => $status === $key,
                        'text-fg-2 hover:text-fg' => $status !== $key,
                    ])>
                    {{ $label }}
                    <span @class(['tabular rounded-control px-1.5 text-caption', 'bg-warning/15 font-semibold text-fg' => $key === 'pending' && $count > 0, 'bg-surface-2 text-fg-2' => ! ($key === 'pending' && $count > 0)])>{{ $count }}</span>
                </a>
            @endforeach
        </nav>

        <form method="GET" role="search" class="flex flex-wrap items-end gap-3 rounded-card border border-line bg-surface p-4 shadow-1 sm:p-5">
            @if ($status !== '')
                <input type="hidden" name="status" value="{{ $status }}">
            @endif
            <x-ui.field label="ค้นหา" for="q" class="min-w-0 flex-1 sm:max-w-md">
                <x-ui.input id="q" name="q" type="search" :value="$q" placeholder="ชื่อธุรกิจ ผู้ติดต่อ เบอร์โทร ชื่อ หรืออีเมลผู้สมัคร" />
            </x-ui.field>
            <x-ui.button type="submit" variant="secondary">ค้นหา</x-ui.button>
            @if ($q !== '')
                <x-ui.button variant="ghost" :href="route('admin.owner-applications.index', array_filter(['status' => $status]))">ล้างคำค้น</x-ui.button>
            @endif
        </form>

        <div class="mt-4">
            @if ($applications->isEmpty())
                <div class="rounded-card border border-line bg-surface shadow-1">
                    <x-ui.empty-state :title="$q !== '' ? 'ไม่พบคำขอที่ตรงกับคำค้น' : ($status === 'pending' ? 'ไม่มีคำขอที่รอพิจารณา' : 'ยังไม่มีคำขอ')"
                        description="ผู้ใช้ยื่นคำขอได้จากเมนูบัญชี เมื่อมีคำขอใหม่จะแสดงที่นี่" />
                </div>
            @else
                <ol class="divide-y divide-line overflow-hidden rounded-card border border-line bg-surface shadow-1">
                    @foreach ($applications as $app)
                        <li class="grid gap-3 px-4 py-4 sm:px-5 md:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)_auto] md:items-center md:gap-6">
                            <div class="min-w-0">
                                <p class="truncate font-semibold text-fg">{{ $app->business_name ?: $app->parking_lot_name }}</p>
                                <p class="truncate text-label text-fg-2">
                                    ลาน "{{ $app->parking_lot_name }}" · ประมาณ <span class="tabular">{{ number_format($app->estimated_slots) }}</span> ช่อง
                                </p>
                                <p class="truncate text-caption text-fg-3">{{ $app->applicant_type === 'company' ? 'บริษัท / นิติบุคคล' : 'บุคคลธรรมดา' }} · โทร <span class="tabular">{{ $app->phone }}</span></p>
                            </div>

                            <div class="min-w-0 text-label">
                                <p class="truncate text-fg">{{ $app->user?->name ?? 'บัญชีถูกลบ' }}</p>
                                <p class="truncate text-fg-2">{{ $app->user?->email }}</p>
                                <p class="text-caption text-fg-3">
                                    ส่งเมื่อ {{ Format::short($app->created_at) }}
                                    @if (! $app->isPending() && $app->reviewer)
                                        · พิจารณาโดย {{ $app->reviewer->name }}
                                    @endif
                                </p>
                            </div>

                            <div class="flex flex-wrap items-center gap-2 md:justify-end">
                                <x-ui.status type="review" :value="$app->status" />
                                <x-ui.button :variant="$app->isPending() ? 'primary' : 'secondary'" size="sm" :href="route('admin.owner-applications.show', $app)">
                                    {{ $app->isPending() ? 'พิจารณา' : 'ดูรายละเอียด' }}
                                </x-ui.button>
                            </div>
                        </li>
                    @endforeach
                </ol>

                <x-ui.pagination :paginator="$applications" class="mt-6" />
            @endif
        </div>
    </div>
</x-app-layout>
