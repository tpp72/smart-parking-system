{{-- <x-ui.pagination :paginator="$reservations" /> — ใช้มุมมองเดียวกับ ->links('vendor.pagination.sp') --}}
@props(['paginator'])

<div {{ $attributes }}>
    {{ $paginator->onEachSide(1)->links('vendor.pagination.sp') }}
</div>
