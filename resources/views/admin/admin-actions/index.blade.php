{{--
    Audit Log — การกระทำของทุกบทบาทและเหตุการณ์ที่ระบบดำเนินการ (รวม Payment Log)
    แสดงชื่อภาษาไทยจาก App\Support\AuditCatalog พร้อมรหัสเดิมตัวเล็ก · รายละเอียด (meta) เป็นคู่ "ชื่อ: ค่า" กดดูเพิ่มได้
    ไฟล์ CSV ยังใช้รหัสและ JSON เดิม · ต้องการ: $rows, $actions, $subjectTypes, $roles, $filters
--}}
@use('App\Support\AuditCatalog')
@use('App\Support\Navigation')

@php
    $roleLabels = Navigation::ROLE_LABELS + ['system' => 'ระบบ'];
    $hasFilter = collect($filters)->except('page')->filter(fn ($v) => filled($v))->isNotEmpty();
    $actionGroups = $actions->groupBy(fn ($a) => AuditCatalog::group($a));
@endphp

<x-app-layout>
    <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-h1 text-fg">Audit Log</h1>
                <p class="mt-1 text-fg-2">ใครทำอะไร เมื่อไร ทั้งผู้ใช้ทุกบทบาทและระบบอัตโนมัติ (รวมการยืนยันรับเงิน)</p>
            </div>
            <div class="flex flex-col items-start gap-1 sm:items-end">
                <x-ui.button variant="secondary" :href="route('admin.admin-actions.export', request()->query())">
                    <x-ui.icon name="export" class="h-4 w-4" /> ส่งออก CSV
                </x-ui.button>
                <span class="text-caption text-fg-3">ใช้ตัวกรองนี้ · ไฟล์ใช้รหัสเดิม</span>
            </div>
        </div>

        <form method="GET" role="search" class="rounded-card border border-line bg-surface p-4 shadow-1 sm:p-5">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-[1.4fr_0.8fr_1.2fr_1fr]">
                <x-ui.field label="ค้นหา" for="q" class="sm:col-span-2 lg:col-span-1">
                    <x-ui.input id="q" name="q" type="search" :value="$filters['q'] ?? ''" placeholder="ชื่อ อีเมล ทะเบียน หรือ #รหัสรายการ" />
                </x-ui.field>
                <x-ui.field label="ผู้กระทำ" for="actor_role">
                    <x-ui.select id="actor_role" name="actor_role" placeholder="ทุกบทบาท">
                        @foreach ($roles as $r)
                            <option value="{{ $r }}" @selected(($filters['actor_role'] ?? '') === $r)>{{ $roleLabels[$r] ?? $r }}</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>
                <x-ui.field label="การกระทำ" for="action">
                    <x-ui.select id="action" name="action" placeholder="ทุกการกระทำ">
                        @foreach ($actionGroups as $group => $items)
                            <optgroup label="{{ $group }}">
                                @foreach ($items as $a)
                                    <option value="{{ $a }}" @selected(($filters['action'] ?? '') === $a)>{{ AuditCatalog::action($a) }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>
                <x-ui.field label="รายการที่ถูกกระทำ" for="subject_type">
                    <x-ui.select id="subject_type" name="subject_type" placeholder="ทุกประเภท">
                        @foreach ($subjectTypes as $s)
                            <option value="{{ $s }}" @selected(($filters['subject_type'] ?? '') === $s)>{{ AuditCatalog::subject($s) }}</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>
                <x-ui.field label="ตั้งแต่วันที่" for="from">
                    <x-ui.input id="from" name="from" data-flatpickr="date" :value="$filters['from'] ?? ''" placeholder="วันที่" />
                </x-ui.field>
                <x-ui.field label="ถึงวันที่" for="to">
                    <x-ui.input id="to" name="to" data-flatpickr="date" :value="$filters['to'] ?? ''" placeholder="วันที่" />
                </x-ui.field>
            </div>
            <div class="mt-4 flex flex-wrap items-center gap-2">
                <x-ui.button type="submit" variant="secondary">ค้นหา</x-ui.button>
                @if ($hasFilter)
                    <x-ui.button variant="ghost" :href="route('admin.admin-actions.index')">ล้างตัวกรอง</x-ui.button>
                @endif
                <p class="ml-auto text-label text-fg-2">พบ <span class="tabular font-semibold text-fg">{{ number_format($rows->total()) }}</span> รายการ</p>
            </div>
        </form>

        <div class="mt-4">
            @if ($rows->isEmpty())
                <div class="rounded-card border border-line bg-surface shadow-1">
                    <x-ui.empty-state :title="$hasFilter ? 'ไม่พบรายการที่ตรงกับตัวกรอง' : 'ยังไม่มีรายการ'" description="การกระทำในระบบจะถูกบันทึกที่นี่โดยอัตโนมัติ" />
                </div>
            @else
                <ol class="divide-y divide-line overflow-hidden rounded-card border border-line bg-surface shadow-1">
                    @foreach ($rows as $r)
                        @php
                            $meta = AuditCatalog::meta($r->meta);
                            $preview = array_slice($meta, 0, 3);
                            $more = array_slice($meta, 3);
                            $isSystem = $r->actor_role === 'system';
                        @endphp
                        <li class="grid gap-2 px-4 py-4 sm:px-5 md:grid-cols-[9.5rem_minmax(0,1fr)] md:gap-5">
                            <div class="text-label">
                                @php $at = \Carbon\Carbon::parse($r->created_at); @endphp
                                <p class="text-fg">{{ $at->isToday() ? 'วันนี้' : ($at->isYesterday() ? 'เมื่อวาน' : $at->format('d/m/Y')) }}</p>
                                <p class="tabular text-fg-2">{{ $at->format('H:i:s') }} น.</p>
                            </div>

                            <div class="min-w-0">
                                <p class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                                    <span class="font-semibold text-fg">{{ AuditCatalog::action($r->action) }}</span>
                                    @if ($r->subject_type)
                                        <span class="text-label text-fg-2">
                                            {{ AuditCatalog::subject($r->subject_type) }}@if ($r->subject_id) <span class="tabular">#{{ $r->subject_id }}</span>@endif
                                        </span>
                                    @endif
                                    <code class="font-mono text-mini text-fg-3">{{ $r->action }}</code>
                                </p>

                                <p class="mt-0.5 text-label text-fg-2">
                                    โดย
                                    @if ($isSystem)
                                        <span class="font-semibold text-fg">ระบบอัตโนมัติ</span>
                                    @else
                                        <span class="font-semibold text-fg">{{ $r->actor_name ?? 'บัญชีถูกลบ' }}</span>
                                        <span class="text-fg-3">({{ $roleLabels[$r->actor_role] ?? $r->actor_role }})</span>
                                        @if ($r->actor_email) · {{ $r->actor_email }} @endif
                                    @endif
                                    @if ($r->ip_address) · <span class="tabular text-fg-3">IP {{ $r->ip_address }}</span> @endif
                                </p>

                                @if ($meta)
                                    <dl class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-caption">
                                        @foreach ($preview as $row)
                                            <div class="flex min-w-0 gap-1">
                                                <dt class="shrink-0 text-fg-3">{{ $row['label'] }}:</dt>
                                                <dd class="min-w-0 break-words text-fg">{{ $row['value'] }}</dd>
                                            </div>
                                        @endforeach
                                    </dl>
                                    @if ($more)
                                        <details class="group mt-1">
                                            <summary class="inline-flex min-h-touch cursor-pointer list-none items-center gap-1 text-caption font-semibold text-primary-ink [&::-webkit-details-marker]:hidden">
                                                <x-ui.icon name="chevron-down" class="h-3.5 w-3.5 transition-transform duration-fast group-open:rotate-180" />
                                                <span class="group-open:hidden">ดูรายละเอียดอีก {{ count($more) }} รายการ</span>
                                                <span class="hidden group-open:inline">ซ่อนรายละเอียด</span>
                                            </summary>
                                            <dl class="grid gap-x-4 gap-y-1 rounded-control bg-surface-2 px-3 py-2 text-caption sm:grid-cols-2">
                                                @foreach ($more as $row)
                                                    <div class="flex min-w-0 gap-1">
                                                        <dt class="shrink-0 text-fg-3">{{ $row['label'] }}:</dt>
                                                        <dd class="min-w-0 break-words text-fg">{{ $row['value'] }}</dd>
                                                    </div>
                                                @endforeach
                                            </dl>
                                        </details>
                                    @endif
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ol>

                <x-ui.pagination :paginator="$rows" class="mt-6" />
            @endif
        </div>
    </div>
</x-app-layout>
