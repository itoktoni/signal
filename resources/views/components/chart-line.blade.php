@props([
    'id' => 'chart',
    'title' => '',
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
            text: '{{ $title }}'
        },
        tooltip: {
            trigger: 'axis'
        },
        legend: {
            data: ['Data']
        },
        xAxis: {
            type: 'category',
            data: @json($categories)
        },
        yAxis: {
            type: 'value'
        },
        series: [{
            name: 'Data',
            type: 'line',
            data: @json($data),
            smooth: true,
            itemStyle: {
                color: '{{ $color }}'
            },
            areaStyle: {
                color: '{{ $color }}20'
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