<x-app-layout>
    <div class="sp-bg min-h-screen text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-extrabold sp-glow-text">ผู้ใช้งาน</h1>
                    <p class="text-gray-300 mt-1">ค้นหา/กรอง/แก้ไข/Force reset password</p>
                </div>
            </div>

            @if (session('success'))
                <div class="sp-card rounded-2xl p-4 mt-6 border border-green-600/40">
                    <p class="text-green-200 font-semibold">{{ session('success') }}</p>
                </div>
            @endif

            <div class="sp-card rounded-2xl p-5 mt-6">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-3">
                    <input name="q" value="{{ $q }}" placeholder="ค้นหา name/email..."
                        class="w-full rounded-xl bg-black/40 border border-red-900/60 text-white placeholder-gray-400 focus:ring-0 focus:border-red-600" />

                    <select name="role" class="sp-select">
                        <option value="">ทุก role</option>
                        <option value="admin" @selected($role === 'admin')>admin</option>
                        <option value="user" @selected($role === 'user')>user</option>
                    </select>

                    <div class="flex gap-2 md:col-span-2">
                        <button class="sp-btn sp-btn-outline" type="submit">ค้นหา</button>
                        <a class="sp-btn sp-btn-outline" href="{{ route('admin.users.index') }}">ล้าง</a>
                    </div>
                </form>
            </div>

            <div class="sp-card rounded-2xl p-6 mt-6 overflow-x-auto">
                <table class="w-full sp-table">
                    <thead>
                        <tr class="border-b sp-divider">
                            <th class="py-3 pr-4">ชื่อ</th>
                            <th class="py-3 pr-4">อีเมล</th>
                            <th class="py-3 pr-4">role</th>
                            <th class="py-3 pr-4">force reset</th>
                            <th class="py-3 pr-4">อัปเดต</th>
                            <th class="py-3 pr-4" style="text-align:right">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($users as $u)
                            <tr class="border-b sp-divider">
                                <td class="py-3 pr-4 font-bold">{{ $u->name }}</td>
                                <td class="py-3 pr-4 text-gray-200">{{ $u->email }}</td>
                                <td class="py-3 pr-4">
                                    @if ($u->role === 'admin')
                                        <span class="sp-badge sp-badge-warn">admin</span>
                                    @else
                                        <span class="sp-badge sp-badge-ok">user</span>
                                    @endif
                                </td>
                                <td class="py-3 pr-4">
                                    @if ($u->force_password_reset)
                                        <span class="sp-badge sp-badge-bad">required</span>
                                    @else
                                        <span class="text-gray-400 text-sm">-</span>
                                    @endif
                                </td>
                                <td class="py-3 pr-4 text-gray-400">{{ $u->updated_at?->format('Y-m-d H:i') }}</td>
                                <td class="py-3 pr-4">
                                    <div class="flex justify-end">
                                        <a href="{{ route('admin.users.edit', $u) }}"
                                            class="sp-btn sp-btn-outline">แก้ไข</a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-10 text-center text-gray-300">ยังไม่มีผู้ใช้</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                <div class="mt-4">
                    {{ $users->links('vendor.pagination.sp') }}
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
