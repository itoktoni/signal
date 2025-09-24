@props([
    'id' => 'lineStackedChart',
    'title' => 'Stacked Line Chart',
    'categories' => [],
    'series' => [],
    'height' => 300,
    'colors' => ['#5470c6', '#91cc75', '#fac858', '#ee6666', '#73c0de']
])

<div class="chart-container">
    <div id="{{ $id }}" style="width: 100%; height: {{ $height }}px;"></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var chart = echarts.init(document.getElementById('{{ $id }}'));
    var colors = @json($colors);

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
            data: @json($series).map(s => s.name),
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
        series: @json($series).map(function(series, index) {
            return {
                name: series.name,
                type: 'line',
                stack: 'Total',
                smooth: true,
                symbol: 'none',
                lineStyle: {
                    width: 2,
                    color: colors[index % colors.length]
                },
                areaStyle: {
                    color: colors[index % colors.length] + '40'
                },
                emphasis: {
                    focus: 'series'
                },
                data: series.data
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