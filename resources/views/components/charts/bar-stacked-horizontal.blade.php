@props([
    'id' => 'barStackedHorizontalChart',
    'title' => 'Stacked Horizontal Bar',
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
            type: 'value',
            axisLabel: {
                formatter: function(value) {
                    return value >= 1000 ? (value / 1000).toFixed(1) + 'k' : value;
                }
            }
        },
        yAxis: {
            type: 'category',
            data: @json($categories),
            axisLabel: {
                interval: 0,
                rotate: 0
            }
        },
        series: @json($series).map(function(series, index) {
            return {
                name: series.name,
                type: 'bar',
                stack: 'total',
                barWidth: '70%',
                data: series.data,
                itemStyle: {
                    color: colors[index % colors.length],
                    borderRadius: index === 0 ? [0, 4, 4, 0] : index === @json($series).length - 1 ? [4, 0, 0, 4] : [0, 0, 0, 0]
                },
                label: {
                    show: true,
                    position: 'inside',
                    formatter: function(params) {
                        var total = 0;
                        @json($series).forEach(function(s) {
                            total += s.data[params.dataIndex];
                        });
                        var percentage = ((params.value / total) * 100).toFixed(1);
                        return percentage + '%';
                    },
                    fontSize: 10,
                    fontWeight: 'bold'
                },
                emphasis: {
                    itemStyle: {
                        shadowBlur: 10,
                        shadowColor: 'rgba(0, 0, 0, 0.3)'
                    }
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