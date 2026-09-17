{{-- สถานะคำขอเป็นเจ้าของลาน: รอพิจารณา / อนุมัติแล้ว / ไม่อนุมัติ (แก้ไขส่งใหม่ได้) --}}
@use('App\Support\Format')

<x-app-layout flash-toast>
    <div class="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        <h1 class="text-h1 text-fg">คำขอเป็นเจ้าของลาน</h1>

        @if (! $application)
            <div class="mt-6 rounded-card border border-line bg-surface shadow-1">
                <x-ui.empty-state title="ยังไม่มีคำขอ" description="สมัครเพื่อเปิดลานจอดของคุณในระบบ">
                    <x-ui.button :href="route('owner.application.create')">สมัครเป็นเจ้าของลาน</x-ui.button>
                </x-ui.empty-state>
            </div>
        @else
            @php
                $state = $application->isPending() ? 'pending' : ($application->isApproved() ? 'approved' : 'rejected');
            @endphp

            <section aria-labelledby="status-title" class="mt-6 rounded-card border border-line bg-surface p-5 shadow-1 sm:p-6">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 id="status-title" class="text-h3 text-fg">สถานะ</h2>
                    <x-ui.status type="review" :value="$state" />
                </div>

                {{-- เส้นทางคำขอ: ส่งคำขอ → พิจารณา → ผล --}}
                <ol class="mt-5 grid gap-3 sm:grid-cols-3">
                    <li class="border-t-2 border-fg-2 pt-2">
                        <p class="text-label font-semibold text-fg">ส่งคำขอ</p>
                        <p class="text-caption text-fg-3">{{ Format::short($application->updated_at ?? $application->created_at) }}</p>
                    </li>
                    <li @class(['border-t-2 pt-2', 'border-primary-ink' => $state === 'pending', 'border-fg-2' => $state !== 'pending'])>
                        <p class="text-label font-semibold text-fg">ผู้ดูแลระบบพิจารณา</p>
                        <p class="text-caption text-fg-3">{{ $state === 'pending' ? 'กำลังพิจารณา · แจ้งผลผ่านการแจ้งเตือน' : Format::short($application->reviewed_at) }}</p>
                    </li>
                    <li @class(['border-t-2 pt-2', 'border-line' => $state === 'pending', 'border-success' => $state === 'approved', 'border-danger' => $state === 'rejected'])>
                        <p class="text-label font-semibold text-fg">{{ ['pending' => 'ผลการพิจารณา', 'approved' => 'อนุมัติแล้ว', 'rejected' => 'ไม่อนุมัติ'][$state] }}</p>
                        <p class="text-caption text-fg-3">{{ ['pending' => 'ยังไม่มีผล', 'approved' => 'บัญชีเป็นเจ้าของลาน', 'rejected' => 'แก้ไขแล้วส่งใหม่ได้'][$state] }}</p>
                    </li>
                </ol>

                @if ($state === 'rejected' && $application->rejection_reason)
                    <x-ui.alert tone="warning" title="เหตุผลที่ไม่อนุมัติ" class="mt-5">{{ $application->rejection_reason }}</x-ui.alert>
                @endif

                @if ($state === 'rejected')
                    <x-ui.button :href="route('owner.application.edit')" class="mt-5">แก้ไขและส่งใหม่</x-ui.button>
                @elseif ($state === 'approved' && auth()->user()->role === 'owner')
                    <x-ui.button :href="route('owner.dashboard')" class="mt-5">ไปที่ภาพรวมลาน</x-ui.button>
                @endif
            </section>

            <section aria-labelledby="detail-title" class="mt-6 rounded-card border border-line bg-surface p-5 shadow-1 sm:p-6">
                <h2 id="detail-title" class="text-h3 text-fg">รายละเอียดคำขอ</h2>
                <dl class="mt-4 grid gap-x-6 gap-y-4 text-label sm:grid-cols-2">
                    @foreach ([
                        'ประเภทผู้สมัคร' => $application->applicant_type === 'company' ? 'บริษัท / นิติบุคคล' : 'บุคคลธรรมดา',
                        'ชื่อธุรกิจ' => $application->business_name,
                        'ผู้ติดต่อ' => $application->contact_name,
                        'เบอร์โทรศัพท์' => $application->phone,
                        'อีเมล' => $application->email,
                        'ชื่อลานจอด' => $application->parking_lot_name,
                        'จำนวนช่องจอดโดยประมาณ' => number_format($application->estimated_slots).' ช่อง',
                        'ที่อยู่' => collect([$application->address, $application->district, $application->province])->filter()->implode(' '),
                    ] as $label => $val)
                        @continue(blank($val))
                        <div>
                            <dt class="text-caption text-fg-3">{{ $label }}</dt>
                            <dd class="mt-0.5 text-fg">{{ $val }}</dd>
                        </div>
                    @endforeach
                    @if ($application->description)
                        <div class="sm:col-span-2">
                            <dt class="text-caption text-fg-3">รายละเอียดเพิ่มเติม</dt>
                            <dd class="mt-0.5 whitespace-pre-line text-fg">{{ $application->description }}</dd>
                        </div>
                    @endif
                    @if ($application->document_path)
                        <div class="sm:col-span-2">
                            <dt class="text-caption text-fg-3">เอกสารแนบ</dt>
                            <dd class="mt-0.5"><a href="{{ route('owner-applications.document', $application) }}" target="_blank" rel="noopener" class="font-semibold text-primary-ink underline-offset-4 hover:underline">เปิดดูเอกสาร</a></dd>
                        </div>
                    @endif
                </dl>
            </section>
        @endif
    </div>
</x-app-layout>
