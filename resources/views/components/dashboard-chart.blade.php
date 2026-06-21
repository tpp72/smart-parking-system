@props([
    'type'     => 'bar',
    'title'    => '',
    'labels'   => [],
    'datasets' => [],
    'height'   => null,
])
@php
    use Illuminate\Support\Str;
    $id           = 'sp-chart-' . Str::random(8);
    $isPie        = in_array($type, ['pie', 'doughnut']);
    $isHorizontal = ($type === 'horizontalBar');
    $jsType       = $isHorizontal ? 'bar' : $type;
    $h            = $height ?? ($isPie ? '280px' : '240px');
@endphp

@once
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
@endonce

<div class="sp-card rounded-2xl p-5 flex flex-col gap-3">
    @if($title)
        <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest">{{ $title }}</h3>
    @endif
    <div style="position:relative;height:{{ $h }}">
        <canvas id="{{ $id }}"></canvas>
    </div>
</div>

<script>
(function () {
    var canvas = document.getElementById('{{ $id }}');
    if (!canvas) return;

    var tickColor  = 'rgba(156,163,175,0.9)';
    var gridColor  = 'rgba(255,255,255,0.06)';
    var isPie      = {!! json_encode($isPie) !!};
    var isHoriz    = {!! json_encode($isHorizontal) !!};

    var options = {
        responsive: true,
        maintainAspectRatio: false,
        animation: { duration: 500, easing: 'easeInOutQuart' },
        plugins: {
            legend: {
                display: isPie,
                position: 'bottom',
                labels: {
                    color: tickColor,
                    padding: 14,
                    font: { size: 11 },
                    boxWidth: 12,
                    boxHeight: 12,
                },
            },
            tooltip: {
                backgroundColor: 'rgba(0,0,0,0.85)',
                titleColor: '#fff',
                bodyColor: tickColor,
                borderColor: 'rgba(255,255,255,0.1)',
                borderWidth: 1,
                padding: 10,
            },
        },
    };

    if (!isPie) {
        if (isHoriz) { options.indexAxis = 'y'; }
        options.scales = {
            x: {
                beginAtZero: true,
                ticks:  { color: tickColor, font: { size: 11 }, maxRotation: 45 },
                grid:   { color: gridColor },
                border: { color: gridColor },
            },
            y: {
                beginAtZero: !isHoriz,
                ticks:  { color: tickColor, font: { size: 11 } },
                grid:   { color: gridColor },
                border: { color: gridColor },
            },
        };
    }

    new Chart(canvas.getContext('2d'), {
        type:    '{{ $jsType }}',
        data:    { labels: @json($labels), datasets: @json($datasets) },
        options: options,
    });
})();
</script>
