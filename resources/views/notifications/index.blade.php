<x-app-layout>
    <div class="sp-bg min-h-screen text-white">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
                <div>
                    <h1 class="text-3xl font-extrabold sp-glow-text">การแจ้งเตือน (Notifications)</h1>
                    <p class="text-gray-300 mt-1">
                        ยังไม่ได้อ่าน
                        <span class="font-bold text-red-300">
                            {{ $notifications->where('is_read', false)->count() }}
                        </span>
                        รายการ
                    </p>
                </div>

                @if ($notifications->where('is_read', false)->count() > 0)
                    <form method="POST" action="{{ route('notifications.read-all') }}">
                        @csrf
                        <button type="submit" class="sp-btn sp-btn-outline">
                            อ่านทั้งหมด (Mark all read)
                        </button>
                    </form>
                @endif
            </div>

            @if (session('success'))
                <div class="sp-card rounded-2xl p-4 mb-4 border border-green-600/40">
                    <p class="text-green-200 font-semibold">{{ session('success') }}</p>
                </div>
            @endif

            <div class="space-y-3">
                @forelse ($notifications as $n)
                    <div @class([
                        'sp-card rounded-2xl p-5 flex items-start justify-between gap-4 transition',
                        'border border-red-600/30 bg-red-900/10' => !$n->is_read,
                        'opacity-60' => $n->is_read,
                    ])>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-1">
                                @if (!$n->is_read)
                                    <span class="inline-block w-2 h-2 rounded-full bg-red-400 shrink-0"></span>
                                @endif
                                <p class="font-bold text-white truncate">{{ $n->title }}</p>
                                <span class="text-xs text-gray-500 shrink-0 ml-auto">
                                    {{ $n->created_at->diffForHumans() }}
                                </span>
                            </div>
                            <p class="text-gray-300 text-sm leading-relaxed">{{ $n->message }}</p>
                        </div>

                        @if (!$n->is_read)
                            <form method="POST"
                                action="{{ route('notifications.read', $n) }}"
                                class="shrink-0">
                                @csrf
                                <button type="submit"
                                    class="sp-btn sp-btn-outline text-xs px-3 py-1 whitespace-nowrap">
                                    อ่านแล้ว
                                </button>
                            </form>
                        @else
                            <span class="text-xs text-gray-500 shrink-0 mt-1">อ่านแล้ว</span>
                        @endif
                    </div>
                @empty
                    <div class="sp-card rounded-2xl p-10 text-center text-gray-400">
                        ไม่มีการแจ้งเตือน
                    </div>
                @endforelse
            </div>

            <div class="mt-6">
                {{ $notifications->links('vendor.pagination.sp') }}
            </div>

        </div>
    </div>
</x-app-layout>
