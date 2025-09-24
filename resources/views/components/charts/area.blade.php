@props([
    'id' => 'areaChart',
    'title' => 'Area Chart',
    'categories' => [],
    'series' => [],
    'height' => 300,
    'colors' => ['#5470c6', '#91cc75', '#fac858', '#ee6666', '#73c0de'],
    'smooth' => true,
    'stacked' => false
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
            var color = colors[index % colors.length];
            return {
                name: series.name,
                type: 'line',
                smooth: @json($smooth),
                symbol: 'none',
                @if($stacked)
                stack: 'total',
                @endif
                lineStyle: {
                    width: 0
                },
                areaStyle: {
                    color: {
                        type: 'linear',
                        x: 0,
                        y: 0,
                        x2: 0,
                        y2: 1,
                        colorStops: [{
                            offset: 0,
                            color: color + '80'
                        }, {
                            offset: 1,
                            color: color + '20'
                        }]
                    }
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