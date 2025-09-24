@props([
    'id' => 'barChart',
    'title' => 'Bar Chart',
    'categories' => [],
    'series' => [],
    'height' => 300,
    'colors' => ['#5470c6', '#91cc75', '#fac858', '#ee6666', '#73c0de'],
    'stacked' => false,
    'horizontal' => false
])

<div class="chart-container">
    <div id="{{ $id }}" style="width: 100%; height: {{ $height }}px;"></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var chart = echarts.init(document.getElementById('{{ $id }}'));
    var colors = @json($colors);
    var isHorizontal = @json($horizontal);

    var option = {
        title: {
            text: '{{ $title }}',
            left: 'center',
            top: 20
        },
        tooltip: {
            trigger: 'axis',
            axisPointer: {
                type: 'shadow'
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
            type: isHorizontal ? 'value' : 'category',
            data: isHorizontal ? null : @json($categories),
            axisLabel: {
                rotate: isHorizontal ? 0 : 45
            }
        },
        yAxis: {
            type: isHorizontal ? 'category' : 'value',
            data: isHorizontal ? @json($categories) : null
        },
        series: @json($series).map(function(series, index) {
            return {
                name: series.name,
                type: 'bar',
                @if($stacked)
                stack: 'total',
                @endif
                barWidth: '60%',
                itemStyle: {
                    color: colors[index % colors.length],
                    borderRadius: [4, 4, 0, 0]
                },
                emphasis: {
                    itemStyle: {
                        color: colors[index % colors.length],
                        shadowBlur: 10,
                        shadowColor: 'rgba(0, 0, 0, 0.3)'
                    }
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