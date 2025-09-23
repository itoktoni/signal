@props([
    'id' => 'lineChart',
    'title' => 'Line Chart',
    'categories' => [],
    'data' => [],
    'height' => 300,
    'color' => '#5470c6',
    'showArea' => true,
    'smooth' => true
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
            left: 'center'
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
            data: ['Data']
        },
        grid: {
            left: '3%',
            right: '4%',
            bottom: '3%',
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
            smooth: @json($smooth),
            symbol: 'none',
            lineStyle: {
                color: '{{ $color }}',
                width: 2
            },
            itemStyle: {
                color: '{{ $color }}'
            },
            @if($showArea)
            areaStyle: {
                color: {
                    type: 'linear',
                    x: 0,
                    y: 0,
                    x2: 0,
                    y2: 1,
                    colorStops: [{
                        offset: 0, color: '{{ $color }}40'
                    }, {
                        offset: 1, color: '{{ $color }}10'
                    }]
                }
            },
            @endif
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