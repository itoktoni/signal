@props([
    'id' => 'areaBasicChart',
    'title' => 'Basic Area Chart',
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
            trigger: 'axis',
            axisPointer: {
                type: 'cross',
                label: {
                    backgroundColor: '#6a7985'
                }
            }
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
            stack: 'Total',
            smooth: false,
            symbol: 'none',
            lineStyle: {
                width: 0
            },
            areaStyle: {
                color: '{{ $color }}'
            },
            emphasis: {
                focus: 'series'
            },
            data: @json($data)
        }]
    };
    chart.setOption(option);

    // Resize handler
    window.addEventListener('resize', function() {
        chart.resize();
    });
});
</script>