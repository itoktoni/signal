@props([
    'id' => 'chart',
    'title' => '',
    'categories' => [],
    'series' => [],
    'height' => 300,
    'colors' => ['#5470c6', '#91cc75']
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
            trigger: 'axis',
            axisPointer: {
                type: 'shadow'
            }
        },
        legend: {
            data: @json($series).map(s => s.name)
        },
        xAxis: {
            type: 'category',
            data: @json($categories)
        },
        yAxis: {
            type: 'value'
        },
        series: @json($series).map(function(series, index) {
            var colors = @json($colors);
            return {
                name: series.name,
                type: 'bar',
                stack: 'total',
                data: series.data,
                itemStyle: {
                    color: colors[index % colors.length]
                }
            };
        })
    };
    chart.setOption(option);

    // Resize handler
    window.addEventListener('resize', function() {
        chart.resize();
    });
});
</script>