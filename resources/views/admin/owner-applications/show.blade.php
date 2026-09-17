{{--
    พิจารณาคำขอเป็นเจ้าของลาน — ผู้สมัคร (บัญชีในระบบ) · รายละเอียดธุรกิจและลาน · เอกสารแนบ
    รอพิจารณา: อนุมัติ (ยืนยันก่อน) หรือไม่อนุมัติ (ต้องระบุเหตุผล ≥ 10 ตัวอักษร ผู้สมัครเห็นเหตุผลนี้)
    ต้องการ: $ownerApplication
--}}
@use('App\Support\Format')
@use('App\Support\Navigation')

@php
    $app = $ownerApplication;
    $applicant = $app->user;
    $canApproveRole = $applicant && $applicant->role === 'user';
    // บุคคลธรรมดาไม่ต้องกรอกชื่อธุรกิจ — ใช้ชื่อลานที่ขอเปิดเป็นหัวข้อแทน
    $title = $app->business_name ?: $app->parking_lot_name;
@endphp

<x-app-layout flash-toast>
    <div class="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        <a href="{{ route('admin.owner-applications.index') }}" class="inline-flex min-h-touch items-center gap-1 text-label font-semibold text-primary-ink underline-offset-4 hover:underline">
            <x-ui.icon name="chevron-right" class="h-4 w-4 rotate-180" /> คำขอเป็นเจ้าของลาน
        </a>
        <div class="mt-2 flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <h1 class="text-h1 text-fg">{{ $title }}</h1>
                <p class="mt-1 text-fg-2">ส่งคำขอเมื่อ {{ Format::short($app->created_at) }}@if ($app->updated_at && $app->updated_at->ne($app->created_at)) · แก้ไขล่าสุด {{ Format::short($app->updated_at) }}@endif</p>
            </div>
            <x-ui.status type="review" :value="$app->status" />
        </div>

        @if ($errors->has('error'))
            <x-ui.alert tone="danger" class="mt-6">{{ $errors->first('error') }}</x-ui.alert>
        @endif

        @if (! $app->isPending())
            <x-ui.alert :tone="$app->status === 'approved' ? 'success' : 'warning'" class="mt-6"
                :title="$app->status === 'approved' ? 'อนุมัติแล้ว' : 'ไม่อนุมัติ'">
                พิจารณาโดย {{ $app->reviewer?->name ?? 'บัญชีถูกลบ' }} · {{ Format::short($app->reviewed_at) }}
                @if ($app->status === 'rejected' && $app->rejection_reason)
                    <span class="mt-1 block">เหตุผล: {{ $app->rejection_reason }}</span>
                @endif
            </x-ui.alert>
        @endif

        {{-- ── ผู้สมัคร ──────────────────────────────────────────────── --}}
        <section aria-labelledby="applicant-title" class="mt-6 rounded-card border border-line bg-surface p-5 shadow-1 sm:p-6">
            <h2 id="applicant-title" class="text-h3 text-fg">ผู้สมัคร</h2>
            <dl class="mt-4 grid gap-x-6 gap-y-4 text-label sm:grid-cols-2">
                <div>
                    <dt class="text-caption text-fg-3">บัญชีในระบบ</dt>
                    <dd class="mt-0.5 text-fg">{{ $applicant?->name ?? 'บัญชีถูกลบ' }}</dd>
                    <dd class="text-fg-2">{{ $applicant?->email }}</dd>
                </div>
                <div>
                    <dt class="text-caption text-fg-3">บทบาทปัจจุบัน</dt>
                    <dd class="mt-0.5 text-fg">{{ $applicant ? (Navigation::ROLE_LABELS[$applicant->role] ?? $applicant->role) : '—' }}</dd>
                </div>
                @foreach ([
                    'ประเภทผู้สมัคร' => $app->applicant_type === 'company' ? 'บริษัท / นิติบุคคล' : 'บุคคลธรรมดา',
                    'ผู้ติดต่อ' => $app->contact_name,
                    'เบอร์โทรศัพท์' => $app->phone,
                    'อีเมลติดต่อ' => $app->email,
                ] as $label => $val)
                    @continue(blank($val))
                    <div>
                        <dt class="text-caption text-fg-3">{{ $label }}</dt>
                        <dd class="mt-0.5 text-fg">{{ $val }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>

        {{-- ── ธุรกิจและลานจอด ─────────────────────────────────────────── --}}
        <section aria-labelledby="business-title" class="mt-6 rounded-card border border-line bg-surface p-5 shadow-1 sm:p-6">
            <h2 id="business-title" class="text-h3 text-fg">ธุรกิจและลานจอด</h2>
            <dl class="mt-4 grid gap-x-6 gap-y-4 text-label sm:grid-cols-2">
                @foreach ([
                    'ชื่อธุรกิจ' => $app->business_name,
                    'ชื่อลานจอด' => $app->parking_lot_name,
                    'จำนวนช่องจอดโดยประมาณ' => number_format($app->estimated_slots).' ช่อง',
                    'ที่อยู่' => collect([$app->address, $app->district, $app->province])->filter()->implode(' '),
                ] as $label => $val)
                    @continue(blank($val))
                    <div>
                        <dt class="text-caption text-fg-3">{{ $label }}</dt>
                        <dd class="mt-0.5 text-fg">{{ $val }}</dd>
                    </div>
                @endforeach
                @if ($app->description)
                    <div class="sm:col-span-2">
                        <dt class="text-caption text-fg-3">รายละเอียดเพิ่มเติม</dt>
                        <dd class="mt-0.5 whitespace-pre-line text-fg">{{ $app->description }}</dd>
                    </div>
                @endif
                <div class="sm:col-span-2">
                    <dt class="text-caption text-fg-3">เอกสารแนบ</dt>
                    <dd class="mt-0.5">
                        @if ($app->document_path)
                            <a href="{{ route('owner-applications.document', $app) }}" target="_blank" rel="noopener" class="font-semibold text-primary-ink underline-offset-4 hover:underline">เปิดดูเอกสาร (แท็บใหม่)</a>
                        @else
                            <span class="text-fg-2">ไม่ได้แนบเอกสาร</span>
                        @endif
                    </dd>
                </div>
            </dl>
        </section>

        {{-- ── พิจารณา ─────────────────────────────────────────────────── --}}
        @if ($app->isPending())
            <section aria-labelledby="review-title" class="mt-6 rounded-card border border-line bg-surface p-5 shadow-1 sm:p-6"
                x-data="{ rejecting: {{ $errors->has('rejection_reason') ? 'true' : 'false' }} }">
                <h2 id="review-title" class="text-h3 text-fg">พิจารณาคำขอ</h2>
                <p class="mt-1 text-label text-fg-2">อนุมัติแล้วบัญชีนี้เป็นเจ้าของลานทันที และเพิ่มลานจอดเองได้ (ระบบไม่สร้างลานให้) · ผู้สมัครได้รับแจ้งผลทุกกรณี</p>

                @unless ($canApproveRole)
                    <x-ui.alert tone="warning" class="mt-4">อนุมัติไม่ได้ — ผู้สมัครต้องเป็นบัญชีผู้ใช้ (ตอนนี้เป็น{{ $applicant ? (Navigation::ROLE_LABELS[$applicant->role] ?? $applicant->role) : 'บัญชีที่ถูกลบ' }})</x-ui.alert>
                @endunless

                <div class="mt-5 flex flex-wrap gap-3" x-show="! rejecting">
                    @if ($canApproveRole)
                        <form method="POST" action="{{ route('admin.owner-applications.approve', $app) }}"
                            data-confirm="อนุมัติ {{ $applicant->name }} ({{ $title }}) เป็นเจ้าของลาน — บัญชีจะเข้าเมนูเจ้าของลานได้ทันที"
                            data-confirm-title="อนุมัติคำขอนี้?" data-confirm-label="อนุมัติ" data-confirm-cancel="ยังไม่อนุมัติ">
                            @csrf
                            <x-ui.button type="submit">อนุมัติ</x-ui.button>
                        </form>
                    @endif
                    <x-ui.button variant="secondary" x-on:click="rejecting = true; $nextTick(() => $refs.reason.focus())">ไม่อนุมัติ…</x-ui.button>
                </div>

                <form method="POST" action="{{ route('admin.owner-applications.reject', $app) }}" x-show="rejecting" x-cloak class="mt-5 flex flex-col gap-4">
                    @csrf
                    <x-ui.field label="เหตุผลที่ไม่อนุมัติ" for="rejection_reason" required hint="อย่างน้อย 10 ตัวอักษร ผู้สมัครจะเห็นเหตุผลนี้และแก้ไขส่งใหม่ได้">
                        <x-ui.textarea id="rejection_reason" name="rejection_reason" rows="4" minlength="10" maxlength="1000" required x-ref="reason">{{ old('rejection_reason') }}</x-ui.textarea>
                    </x-ui.field>
                    <div class="flex flex-wrap gap-3">
                        <x-ui.button type="submit" variant="danger">ยืนยันไม่อนุมัติ</x-ui.button>
                        <x-ui.button variant="ghost" x-on:click="rejecting = false">กลับ</x-ui.button>
                    </div>
                </form>
            </section>
        @endif
    </div>
</x-app-layout>
