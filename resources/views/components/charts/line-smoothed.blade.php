@props([
    'id' => 'lineSmoothedChart',
    'title' => 'Smoothed Line Chart',
    'categories' => [],
    'data' => [],
    'height' => 300,
    'color' => '#5470c6'
])

<div class="chart-container">
    <div id="{{ $id }}" style="width: 100%; height: {{ $height }}px;"></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var chart = echarts.init(document.getElementById('{{ $id }}'));
    var option = {
        title: {
            text: '{{ $title }}',
            left: 'center',
            top: 20
        },
        tooltip: {
            trigger: 'axis'
        },
        legend: {
            data: ['Data'],
            top: 50
        },
        grid: {
            left: '3%',
            right: '4%',
            bottom: '3%',
            top: '15%',
            containLabel: true
        },
        xAxis: {
            type: 'category',
            boundaryGap: false,
            data: @json($categories)
        },
        yAxis: {
            type: 'value'
        },
        series: [{
            name: 'Data',
            type: 'line',
            smooth: true,
            symbol: 'none',
            sampling: 'average',
            data: @json($data),
            itemStyle: {
                color: '{{ $color }}'
            },
            lineStyle: {
                color: '{{ $color }}',
                width: 3
            },
            areaStyle: {
                color: {
                    type: 'linear',
                    x: 0,
                    y: 0,
                    x2: 0,
                    y2: 1,
                    colorStops: [{
                        offset: 0, color: '{{ $color }}20'
                    }, {
                        offset: 1, color: '{{ $color }}05'
                    }]
                }
            }
        }]
    };
    chart.setOption(option);

    // Resize handler
    window.addEventListener('resize', function() {
        chart.resize();
    });
});
</script>